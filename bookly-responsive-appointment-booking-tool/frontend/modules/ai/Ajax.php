<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Lib;

/**
 * AJAX handlers for the Cloud AI round trip.
 *
 * aiSendMessage() queues an AiJob and fires a fire-and-forget loopback
 * request; aiWorker() (running in that separate request) does the actual
 * tool-loop and self-chains via another loopback if it runs long; aiPoll()
 * reads status from the DB. This avoids holding one blocking connection
 * open for the whole tool-loop, which matters on tight shared-hosting
 * execution limits.
 *
 * Registered by Lib\Base\Ajax::init() as:
 *   - wp_ajax_bookly_ai_send_message   (method aiSendMessage)
 *   - wp_ajax_bookly_ai_worker         (method aiWorker)
 *   - wp_ajax_bookly_ai_poll           (method aiPoll)
 */
class Ajax extends Lib\Base\Ajax
{
    // aiWorker() stops starting new tool-loop rounds once less than
    // WORKER_MARGIN seconds are left in its WORKER_TIME_BUDGET and hands off
    // to a fresh loopback request instead — see respawnWorker().
    const WORKER_TIME_BUDGET = 20;
    const WORKER_MARGIN      = 5;

    // Hard cap on model<->tool round trips per AiJob (per user message, not
    // per whole conversation), independent of the time budget — guards
    // against a model stuck calling tools forever.
    const MAX_STEPS = 12;

    // Which Cloud agent definition serves this widget — see
    // AIBundle/Resources/config/agents.yml in bookly-cloud.
    const AGENT = 'booking';

    protected static function permissions()
    {
        return array( '_default' => 'anonymous' );
    }

    /**
     * Start (or continue) a conversation: persists the user's message,
     * queues an AiJob, and fires a fire-and-forget loopback request to run
     * it (spawnWorker()) — then returns immediately. The browser is expected
     * to start polling aiPoll() with the returned conversation_id.
     *
     * Expects (via json_data): message, conversation_id (optional).
     */
    public static function aiSendMessage()
    {
        $text            = trim( (string) self::parameter( 'message', '' ) );
        $conversation_id = (int) self::parameter( 'conversation_id', 0 );

        if ( $text === '' ) {
            wp_send_json( array( 'success' => false, 'error' => 'ERROR_EMPTY_MESSAGE' ) );
        }

        $conversation = $conversation_id ? Lib\Entities\AiConversation::find( $conversation_id ) : false;
        if ( ! $conversation ) {
            $conversation = new Lib\Entities\AiConversation();
        }
        $conversation->setStatus( Lib\Entities\AiConversation::STATUS_PROCESSING )->save();

        $message = new Lib\Entities\AiMessage();
        $message
            ->setConversationId( $conversation->getId() )
            ->setRole( Lib\Entities\AiMessage::ROLE_USER )
            ->setContent( $text )
            ->save();

        $job = new Lib\Entities\AiJob();
        $job
            ->setConversationId( $conversation->getId() )
            ->setStatus( Lib\Entities\AiJob::STATUS_QUEUED )
            ->save();

        self::spawnWorker( $job );

        wp_send_json( array(
            'success'         => true,
            'conversation_id' => $conversation->getId(),
            'last_message_id' => $message->getId(),
        ) );
    }

    /**
     * The actual agent loop — runs in its OWN php-fpm process (a loopback
     * request, never the browser's own request), so it can safely take up
     * to WORKER_TIME_BUDGET seconds. Does not echo anything the browser
     * depends on — the browser only reads progress via aiPoll().
     *
     * One iteration = one Cloud /complete call + (if the model asked for
     * tools) executing every requested tool and persisting its result. Runs
     * until either: the model stops requesting tools (done), an error
     * occurs (failed), MAX_STEPS is hit (failed), or the time budget is
     * nearly spent (hands off to a fresh loopback via respawnWorker()).
     *
     * Expects (via json_data): job_id.
     */
    public static function aiWorker()
    {
        $job_id = (int) self::parameter( 'job_id', 0 );

        // Atomic claim: only the request that flips queued->running "wins" —
        // a duplicate/replayed loopback just no-ops here instead of running
        // the loop twice.
        $claimed = Lib\Entities\AiJob::query()
            ->update()
            ->setRaw( '`status` = %s, `heartbeat_at` = %s, `updated_at` = %s, `attempts` = `attempts` + 1', array(
                Lib\Entities\AiJob::STATUS_RUNNING,
                current_time( 'mysql' ),
                current_time( 'mysql' ),
            ) )
            ->where( 'id', $job_id )
            ->where( 'status', Lib\Entities\AiJob::STATUS_QUEUED )
            ->execute();

        if ( ! $claimed ) {
            wp_die();
        }

        $job          = Lib\Entities\AiJob::find( $job_id );
        $conversation = $job ? Lib\Entities\AiConversation::find( $job->getConversationId() ) : false;

        if ( ! $job || ! $conversation ) {
            wp_die();
        }

        // No browser is waiting on this request — keep working even after
        // the initiator returns, and don't let the host's default execution
        // limit cut the loop off before WORKER_TIME_BUDGET does.
        ignore_user_abort( true );
        @set_time_limit( 0 );

        $deadline = microtime( true ) + self::WORKER_TIME_BUDGET;
        $step     = $job->getStep();

        while ( $step < self::MAX_STEPS ) {
            $step++;

            $payload = self::buildCompletionPayload( $conversation );
            $client  = Lib\Cloud\API::getInstance()->getProduct( Lib\Cloud\Account::PRODUCT_AI );
            $raw     = $client->completeRaw( $payload );
            $body    = json_decode( $raw['body'], true );

            if ( $raw['status'] !== 200 || empty( $body['success'] ) ) {
                // On the failure path $body['message'] is Cloud's human-readable
                // detail string, not the {role,content,tool_calls} object it is
                // on the success path below.
                $error_code   = isset( $body['error'] ) ? $body['error'] : 'ERROR_PROXY_CONNECTION';
                $error_detail = isset( $body['message'] ) && is_string( $body['message'] ) ? $body['message'] : null;
                self::failJob( $job, $conversation, $error_code, $error_detail );

                return;
            }

            $assistant_message = new Lib\Entities\AiMessage();
            $assistant_message
                ->setConversationId( $conversation->getId() )
                ->setRole( Lib\Entities\AiMessage::ROLE_ASSISTANT )
                ->setContent( isset( $body['message']['content'] ) ? $body['message']['content'] : null )
                ->setToolCalls( ! empty( $body['message']['tool_calls'] ) ? wp_json_encode( $body['message']['tool_calls'] ) : null )
                ->save();

            $job->setStep( $step )->setHeartbeatAt( current_time( 'mysql' ) )->save();

            if ( $body['finish_reason'] !== 'tool_calls' || empty( $body['message']['tool_calls'] ) ) {
                // Model gave a final answer — conversation turn is done.
                $conversation->setStatus( Lib\Entities\AiConversation::STATUS_DONE )->save();
                $job->setStatus( Lib\Entities\AiJob::STATUS_DONE )->save();

                return;
            }

            // Execute every requested tool and persist each result as its
            // own 'tool' message BEFORE the next /complete call — Cloud
            // expects one {role:"tool", tool_call_id, name, content} entry
            // per tool_call echoed back in "messages" (verified against the
            // real Cloud contract, see http/rest-ai.http in bookly-cloud).
            foreach ( $body['message']['tool_calls'] as $tool_call ) {
                $tool_result = self::executeToolForModel( $tool_call['name'], (array) $tool_call['arguments'] );

                $tool_message = new Lib\Entities\AiMessage();
                $tool_message
                    ->setConversationId( $conversation->getId() )
                    ->setRole( Lib\Entities\AiMessage::ROLE_TOOL )
                    ->setToolCallId( $tool_call['id'] )
                    ->setToolName( $tool_call['name'] )
                    ->setContent( $tool_result )
                    ->save();
            }

            if ( microtime( true ) > $deadline - self::WORKER_MARGIN ) {
                self::respawnWorker( $job );

                return;
            }
        }

        self::failJob( $job, $conversation, 'ERROR_MAX_STEPS' );
    }

    /**
     * Read-only status/transcript poll for the browser. Only surfaces
     * 'assistant' messages — 'tool' turns are an implementation detail of
     * the loop.
     */
    public static function aiPoll()
    {
        $conversation_id = (int) self::parameter( 'conversation_id', 0 );
        $after_id        = (int) self::parameter( 'after_id', 0 );

        $conversation = Lib\Entities\AiConversation::find( $conversation_id );
        if ( ! $conversation ) {
            wp_send_json( array( 'success' => false, 'error' => 'ERROR_UNKNOWN_CONVERSATION' ) );
        }

        /** @var Lib\Entities\AiMessage[] $messages */
        $messages = Lib\Entities\AiMessage::query()
            ->where( 'conversation_id', $conversation_id )
            ->whereGt( 'id', $after_id )
            ->where( 'role', Lib\Entities\AiMessage::ROLE_ASSISTANT )
            ->sortBy( 'id' )
            ->find();

        wp_send_json( array(
            'success'    => true,
            'status'     => $conversation->getStatus(),
            'error_code' => $conversation->getStatus() === Lib\Entities\AiConversation::STATUS_ERROR ? $conversation->getErrorCode() : null,
            'messages'   => array_map( function ( Lib\Entities\AiMessage $message ) {
                return array(
                    'id'      => $message->getId(),
                    'content' => $message->getContent(),
                );
            }, $messages ),
        ) );
    }

    /**
     * Fire the fire-and-forget loopback request that runs aiWorker() in a
     * separate php-fpm process. 'blocking' => false means this only
     * initiates the request and returns immediately. Public so
     * Routines::handleAiJobsWatchdog() (stuck-job recovery) can call it too.
     *
     * The nonce must be created as a fully anonymous (uid 0, no session)
     * visitor, not as whoever is logged into the calling request.
     * wp_create_nonce() folds in two things tied to the calling request's
     * login state, both of which must be neutralized:
     *   1. get_current_user_id() — handled by wp_set_current_user( 0 ).
     *   2. wp_get_session_token() — reads $_COOKIE[ LOGGED_IN_COOKIE ]
     *      directly and is unaffected by wp_set_current_user(), so that
     *      cookie must also be hidden for the duration of this call.
     * Without this, aiWorker() rejects the nonce (uid 0 + real session token
     * mismatch).
     *
     * @param Lib\Entities\AiJob $job
     */
    public static function spawnWorker( $job )
    {
        $current_user_id = get_current_user_id();
        $logged_in_cookie = isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? $_COOKIE[ LOGGED_IN_COOKIE ] : null;

        wp_set_current_user( 0 );
        unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
        $nonce = wp_create_nonce( 'bookly' );
        wp_set_current_user( $current_user_id );
        if ( $logged_in_cookie !== null ) {
            $_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
        }

        wp_remote_post( admin_url( 'admin-ajax.php' ), array(
            'blocking'   => false,
            'timeout'    => 5,
            'sslverify'  => false,
            'body'       => array(
                'action'     => 'bookly_ai_worker',
                'csrf_token' => $nonce,
                'job_id'     => $job->getId(),
            ),
        ) );
    }

    /**
     * Hand a job back to 'queued' and spawn a fresh worker for it — the
     * self-chaining handoff used when aiWorker() runs low on time budget.
     *
     * Known gap: if the new loopback never starts (host blocks
     * self-requests), the job sits at 'queued' forever — the watchdog
     * (Routines::handleAiJobsWatchdog()) only catches status='running' with
     * a stale heartbeat, not this case.
     *
     * @param Lib\Entities\AiJob $job
     */
    private static function respawnWorker( $job )
    {
        $job->setStatus( Lib\Entities\AiJob::STATUS_QUEUED )->save();
        self::spawnWorker( $job );
    }

    /**
     * Mark a job (and its conversation) as failed and log why.
     *
     * @param Lib\Entities\AiJob          $job
     * @param Lib\Entities\AiConversation $conversation
     * @param string                      $error_code   Machine-readable code, persisted on the
     *                                                   conversation so aiPoll() can surface it to
     *                                                   the frontend.
     * @param string|null                 $error_detail Human-readable detail (Cloud's $body['message']
     *                                                   on the failure path) — logged only, never
     *                                                   sent to the browser.
     */
    private static function failJob( $job, $conversation, $error_code, $error_detail = null )
    {
        $job->setStatus( Lib\Entities\AiJob::STATUS_FAILED )->save();
        $conversation->setStatus( Lib\Entities\AiConversation::STATUS_ERROR )->setErrorCode( $error_code )->save();

        Lib\Utils\Log::error( 'AI worker job #' . $job->getId() . ' failed: ' . ( $error_detail ? ( $error_code . ': ' . $error_detail ) : $error_code ), __FILE__, __LINE__ );
    }

    /**
     * Execute a tool by name and return a plain string — the shape Cloud
     * expects as a 'tool' message's "content". Never throws — an unknown
     * tool or a runtime error both become a short "Error: ..." string the
     * model can react to instead of aborting the whole conversation turn.
     *
     * @param string $name
     * @param array  $arguments
     * @return string
     */
    private static function executeToolForModel( $name, array $arguments )
    {
        $tools = Tools::all();

        if ( $name === '' || ! isset( $tools[ $name ] ) ) {
            return 'Error: no such tool "' . $name . '".';
        }

        try {
            return (string) $tools[ $name ]->execute( $arguments );
        } catch ( \Error $e ) {
            return 'Error: ' . $e->getMessage();
        } catch ( \Exception $e ) {
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Build one /complete request body from a conversation's full message
     * history — system prompt and tool schema first (stable across the
     * whole tool-loop), history last (the only part that grows turn to
     * turn).
     *
     * @param Lib\Entities\AiConversation $conversation
     * @return array
     */
    private static function buildCompletionPayload( $conversation )
    {
        $messages = array();
        $last_user = null;
        $last_user_created_at = null;

        /** @var Lib\Entities\AiMessage[] $history */
        $history = Lib\Entities\AiMessage::query()
            ->where( 'conversation_id', $conversation->getId() )
            ->sortBy( 'id' )
            ->find();

        foreach ( $history as $message ) {
            switch ( $message->getRole() ) {
                case Lib\Entities\AiMessage::ROLE_USER:
                    $messages[] = array( 'role' => 'user', 'content' => $message->getContent() );
                    $last_user = count( $messages ) - 1;
                    $last_user_created_at = $message->getCreatedAt();
                    break;

                case Lib\Entities\AiMessage::ROLE_ASSISTANT:
                    $entry = array( 'role' => 'assistant', 'content' => $message->getContent() );
                    if ( $message->getToolCalls() ) {
                        $tool_calls = json_decode( $message->getToolCalls(), true );
                        // A tool called with no arguments round-trips as an empty
                        // PHP array, which wp_json_encode() would re-serialize as
                        // "[]" instead of "{}" — Anthropic rejects that echoed-back
                        // empty array as an invalid tool_use.input. Force it back
                        // to an object.
                        foreach ( $tool_calls as &$tool_call ) {
                            if ( isset( $tool_call['arguments'] ) && $tool_call['arguments'] === array() ) {
                                $tool_call['arguments'] = new \stdClass();
                            }
                        }
                        unset( $tool_call );
                        $entry['tool_calls'] = $tool_calls;
                    }
                    $messages[] = $entry;
                    break;

                case Lib\Entities\AiMessage::ROLE_TOOL:
                    $messages[] = array(
                        'role'         => 'tool',
                        'tool_call_id' => $message->getToolCallId(),
                        'name'         => $message->getToolName(),
                        'content'      => $message->getContent(),
                    );
                    break;
            }
        }

        // The clock rides with the customer's message, not in the system
        // prompt: Cloud caches the prompt prefix, and a value that changes
        // every second would invalidate that cache on every call. Pinning it
        // to the message's own created_at also keeps it identical across the
        // 3-5 calls of one tool loop. See SystemPrompt::dateLine().
        if ( $last_user !== null ) {
            $messages[ $last_user ]['content'] .= "\n\n" . SystemPrompt::dateLine( $last_user_created_at );
        }

        return array(
            // Picks the agent definition on the Cloud side (prompt, provider,
            // model, token cap). Sending it explicitly rather than relying on
            // Cloud's default keeps this widget's traffic identifiable in
            // Cloud's logs once other agents exist.
            'agent' => self::AGENT,
            'system' => SystemPrompt::build(),
            // Schemas are ours to define and Cloud passes them through as they
            // arrive: which tools exist here depends on this plugin's version
            // and on the active add-ons, so the set is only knowable on this
            // side, and it is current by construction.
            'tools' => array_values( array_map( function( BookingTools\ToolInterface $tool ) {
                return $tool->getSchema();
            }, Tools::all() ) ),
            'messages' => $messages,
        );
    }
}
