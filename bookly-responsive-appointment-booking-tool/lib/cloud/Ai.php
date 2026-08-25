<?php
namespace Bookly\Lib\Cloud;

class Ai extends Product
{
    const ACTIVATE                = '/1.0/users/%token%/products/ai/activate';               // POST
    const DEACTIVATE_NOW          = '/1.0/users/%token%/products/ai/deactivate/now';          // POST
    const DEACTIVATE_NEXT_RENEWAL = '/1.0/users/%token%/products/ai/deactivate/next-renewal'; // POST
    const REVERT_CANCEL           = '/1.0/users/%token%/products/ai/revert-cancel';           // POST
    // One route for every agent; which one runs is the 'agent' field of the
    // request body (see Frontend\Modules\Ai\Ajax::buildCompletionPayload()).
    // Cloud resolves it to a prompt, provider, model and token cap server-side.
    const COMPLETE                = '/1.0/users/%token%/products/ai/complete';                // POST

    public function completeRaw( array $payload )
    {
        $url = $this->api->buildUrl( self::COMPLETE );

        $response = wp_remote_post( $url, array(
            'method'    => 'POST',
            'timeout'   => 60,
            'sslverify' => false,
            'headers'   => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body' => wp_json_encode( $payload ),
        ) );

        if ( $response instanceof \WP_Error ) {
            return array(
                'status' => 502,
                'body'   => wp_json_encode( array(
                    'success' => false,
                    'error'   => 'ERROR_PROXY_CONNECTION',
                    'message' => implode( '; ', $response->get_error_messages() ),
                ) ),
            );
        }

        return array(
            'status' => wp_remote_retrieve_response_code( $response ) ?: 200,
            'body'   => wp_remote_retrieve_body( $response ),
        );
    }
}
