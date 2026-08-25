<?php
namespace Bookly\Backend\Modules\Appearance;

use Bookly\Lib;

class ModernAppearance extends Lib\Base\Component
{
    public static function render()
    {
        self::enqueueScripts( array(
            'module' => array(
                'js/modern-appearance.js' => array( 'jquery', 'bookly-backend-globals' ),
            ),
        ) );
        self::enqueueStyles( array(
            'bookly' => array( 'backend/resources/css/fontawesome-all.min.css' => array( 'bookly-backend-globals' ) ),
        ) );

        $ai_active = Lib\Cloud\API::getInstance()->account->productActive( Lib\Cloud\Account::PRODUCT_AI );

        // Catalog display order is driven by `pos` (sorted below after the add-on
        // types are merged in): the AI assistant and the best-converting forms open
        // the list, the classic step-by-step and the cancellation confirmation close
        // it. Types without a pos land in the middle (50).
        $appearances = array(
            Lib\Entities\Form::TYPE_BOOKLY_FORM => array(
                'id' => Lib\Entities\Form::TYPE_BOOKLY_FORM,
                'title' => __( 'Step by step form', 'bookly-responsive-appointment-booking-tool' ),
                'description' => __( 'Classic booking form with the consequent scheduling process.', 'bookly-responsive-appointment-booking-tool' ),
                'img' => plugins_url( 'backend/modules/appearance/resources/images/appearance-bookly-form.png', Lib\Plugin::getMainFile() ),
                'url' => add_query_arg( array( 'page' => Page::pageSlug() ), admin_url( 'admin.php' ) ) . '&' . Lib\Entities\Form::TYPE_BOOKLY_FORM,
                'pos' => 90,
            ),
            Lib\Entities\Form::TYPE_AI_ASSISTANT => array(
                'id' => Lib\Entities\Form::TYPE_AI_ASSISTANT,
                'title' => __( 'AI assistant', 'bookly-responsive-appointment-booking-tool' ),
                'description' => __( 'Chat widget that answers customer questions and books appointments for them right on your website.', 'bookly-responsive-appointment-booking-tool' ),
                'img' => plugins_url( 'backend/modules/appearance/resources/images/ai-assistant-form.png', Lib\Plugin::getMainFile() ),
                'appearance' => self::getAppearance( Lib\Entities\Form::TYPE_AI_ASSISTANT ),
                'url' => add_query_arg( array( 'page' => Page::pageSlug() ), admin_url( 'admin.php' ) ) . '&' . Lib\Entities\Form::TYPE_AI_ASSISTANT,
                'cloud_active' => $ai_active,
                'pos' => 10,
            ),
        );
        if ( ! Lib\Config::proActive() ) {
            // Free build: a regular-looking catalog card for the modern booking form.
            // Opening it shows a promo screen (screenshots of two themed forms and an
            // upgrade link) instead of the forms list — the form itself is not shipped
            // with the free build. Deliberately worded as "upgrade", not a product name.
            $appearances['modern-form-promo'] = array(
                'id' => 'modern-form-promo',
                'title' => __( 'Modern booking form', 'bookly-responsive-appointment-booking-tool' ),
                'description' => __( 'Modern, fast, and smooth form that makes booking easy and enjoyable for your customers.', 'bookly-responsive-appointment-booking-tool' ),
                'img' => plugins_url( 'backend/modules/appearance/resources/images/appearance-modern-form-card.png', Lib\Plugin::getMainFile() ),
                'url' => add_query_arg( array( 'page' => Page::pageSlug() ), admin_url( 'admin.php' ) ) . '&modern-form-promo',
                'pos' => 95,
                'promo' => array(
                    'screenshots' => array(
                        plugins_url( 'backend/modules/appearance/resources/images/appearance-modern-form-1.png', Lib\Plugin::getMainFile() ),
                        plugins_url( 'backend/modules/appearance/resources/images/appearance-modern-form-2.png', Lib\Plugin::getMainFile() ),
                    ),
                    'text' => __( 'The modern booking form is available in paid versions of Bookly. Upgrade to get a sleek one-page booking flow with customizable themes, search, catalog and checkout forms.', 'bookly-responsive-appointment-booking-tool' ),
                    'button' => __( 'Upgrade Bookly', 'bookly-responsive-appointment-booking-tool' ),
                    'url' => add_query_arg( array( 'page' => \Bookly\Backend\Modules\Shop\Page::pageSlug() ), admin_url( 'admin.php' ) ),
                ),
            );
        }
        $appearances = array_merge( $appearances, Proxy\Pro::getAppearanceTypes() ?: array() );
        uasort( $appearances, function ( $a, $b ) {
            $a_pos = isset( $a['pos'] ) ? $a['pos'] : 50;
            $b_pos = isset( $b['pos'] ) ? $b['pos'] : 50;

            return $a_pos - $b_pos;
        } );

        $data = array(
            'show_notice' => get_user_meta( get_current_user_id(), Lib\Plugin::getPrefix() . 'dismiss_modern_appearance_notice', true ) ? 0 : 1,
            'appearances' => $appearances,
            'images' => plugins_url( 'frontend/resources/images/', Lib\Plugin::getMainFile() ),
            'moment_format_time' => Lib\Utils\DateTime::convertFormat( 'time', Lib\Utils\DateTime::FORMAT_MOMENT_JS ),
            'cloud_products_url' => add_query_arg(
                array( 'page' => \Bookly\Backend\Modules\CloudProducts\Page::pageSlug() ),
                admin_url( 'admin.php' )
            ),
            'l10n' => array(
                'error' => __( 'Error', 'bookly-responsive-appointment-booking-tool' ),
                'add_new_form' => __( 'Add new form', 'bookly-responsive-appointment-booking-tool' ),
                'back' => __( 'Back', 'bookly-responsive-appointment-booking-tool' ),
                'save' => __( 'Save', 'bookly-responsive-appointment-booking-tool' ),
                'general' => __( 'General', 'bookly-responsive-appointment-booking-tool' ),
                'are_you_sure_delete' => __( 'Are you sure?', 'bookly-responsive-appointment-booking-tool' ),
                'are_you_sure_clone' => __( 'Are you sure?', 'bookly-responsive-appointment-booking-tool' ),
                'are_you_sure_slug' => __( 'Are you sure you want to change the slug? Changing the slug may lead to unexpected behavior.', 'bookly-responsive-appointment-booking-tool' ),
                'copy_shortcode' => __( 'Copy shortcode', 'bookly-responsive-appointment-booking-tool' ),
                'clone_form' => __( 'Clone form', 'bookly-responsive-appointment-booking-tool' ),
                'delete_form' => __( 'Delete form', 'bookly-responsive-appointment-booking-tool' ),
                'settings' => __( 'Settings', 'bookly-responsive-appointment-booking-tool' ),
                'step_settings' => __( 'Step settings', 'bookly-responsive-appointment-booking-tool' ),
                'custom_css' => __( 'Custom CSS', 'bookly-responsive-appointment-booking-tool' ),
                'save_to_apply' => __( 'Save the appearance to apply changes.', 'bookly-responsive-appointment-booking-tool' ),
                'saved' => __( 'Changes saved.', 'bookly-responsive-appointment-booking-tool' ),
                'dropdown_texts' => array(
                    'selectAll' => __( 'Select all', 'bookly-responsive-appointment-booking-tool' ),
                    'allSelected' => __( 'All', 'bookly-responsive-appointment-booking-tool' ),
                    'nothingSelected' => __( 'Nothing selected', 'bookly-responsive-appointment-booking-tool' ),
                    'unknownSelected' => __( 'N/A', 'bookly-responsive-appointment-booking-tool' ),
                ),
                'help' => __( 'To learn more about this feature, please follow <a href="%s" target="_blank">this link</a>.', 'bookly-responsive-appointment-booking-tool' ),
                'notice' => __( 'How to publish this form on your web site?', 'bookly-responsive-appointment-booking-tool' ) .
                    '<br/>' . __( 'Select the form you want to publish, click on the menu button, and select \'Copy shortcode\'. Open the page where you want to add the booking form in a page edit mode and paste the previously copied shortcode. The form will be added to the page.', 'bookly-responsive-appointment-booking-tool' ),
                'ai_activation_title' => __( 'Your AI assistant is ready — turn it on in Bookly Cloud', 'bookly-responsive-appointment-booking-tool' ),
                'ai_activation_text' => __( 'Setup is complete. Once activated, your AI assistant can help answer customer questions, gather the details needed for a booking, and guide customers toward scheduling an appointment — around the clock.', 'bookly-responsive-appointment-booking-tool' ),
                'ai_activation_button' => __( 'Activate AI assistant', 'bookly-responsive-appointment-booking-tool' ),
                'ai_activation_trust' => __( 'Takes under 2 minutes', 'bookly-responsive-appointment-booking-tool' ),
            ),
            'fields' => array(
                'form_title' => __( 'Form title', 'bookly-responsive-appointment-booking-tool' ),
                'form_slug' => __( 'Slug', 'bookly-responsive-appointment-booking-tool' ),
                'main_color' => __( 'Main color', 'bookly-responsive-appointment-booking-tool' ),
                'display_mode' => __( 'Display mode', 'bookly-responsive-appointment-booking-tool' ),
                'display_mode_floating' => __( 'Floating bubble', 'bookly-responsive-appointment-booking-tool' ),
                'display_mode_embedded' => __( 'Embedded', 'bookly-responsive-appointment-booking-tool' ),
                'ai_reset_chat_tooltip' => __( 'Tooltip', 'bookly-responsive-appointment-booking-tool' ),
                'ai_close_chat_tooltip' => __( 'Tooltip', 'bookly-responsive-appointment-booking-tool' ),
                'ai_open_chat_tooltip' => __( 'Tooltip', 'bookly-responsive-appointment-booking-tool' ),
                'ai_send_tooltip' => __( 'Tooltip', 'bookly-responsive-appointment-booking-tool' ),
                'ai_error_messages_hint' => __( 'Shown inside the conversation when something goes wrong.', 'bookly-responsive-appointment-booking-tool' ),
                'ai_error_label' => __( 'Message send failed', 'bookly-responsive-appointment-booking-tool' ),
                'ai_error_quota_label' => __( 'Assistant unavailable', 'bookly-responsive-appointment-booking-tool' ),
                'ai_error_quota_hint' => __( 'Shown to all visitors once your AI usage limit is reached.', 'bookly-responsive-appointment-booking-tool' ),
                // 'Question bubble'
                'ai_question_bubble_color' => __( 'Color', 'bookly-responsive-appointment-booking-tool' ),
                // 'Answer bubble'
                'ai_answer_bubble_color' => __( 'Color', 'bookly-responsive-appointment-booking-tool' ),
                'ai_preview_question' => 'Do you have any openings tomorrow morning?',
                'ai_preview_answer' => 'Yes, 10:00 and 11:30 are free. Would you like me to book one for you?',
            ),
        );

        $data = Proxy\Pro::prepareAppearanceData( $data );

        wp_localize_script( 'bookly-modern-appearance.js', 'BooklyL10nModernAppearance', $data );

        return self::renderTemplate( 'modern_index' );
    }

    /**
     * Load a saved form's settings by token (used by the frontend renderer so a published
     * embed keeps working even if Pro later deactivates).
     *
     * @param string $form_type
     * @param string $token
     * @return array
     */
    public static function getAppearance( $form_type = null, $token = null )
    {
        $appearance = array();
        if ( $token && $data = Lib\Entities\Form::query()->where( 'token', $token )->fetchRow() ) {
            $appearance = $data;
        }

        return self::prepareAppearanceSettings( $form_type, $appearance ?: null );
    }

    /**
     * @param string $form_type
     * @param array $db_appearance
     * @return array
     */
    public static function prepareAppearanceSettings( $form_type, $db_appearance )
    {
        switch ( $form_type ) {
            case Lib\Entities\Form::TYPE_BOOKLY_FORM:
                $appearance = array();
                break;
            case Lib\Entities\Form::TYPE_AI_ASSISTANT:
                $appearance = self::getAiAssistantDefaults();
                break;
            default:
                $appearance = Proxy\Pro::getAppearanceDefaults( $form_type ) ?: array();
        }

        if ( $db_appearance && isset( $db_appearance['settings'] ) && $db_appearance['settings'] ) {
            $settings = json_decode( $db_appearance['settings'], true );
            if ( isset( $appearance['details_fields_order'], $settings['details_fields_order'] ) && is_array( $settings['details_fields_order'] ) ) {
                foreach ( $appearance['details_fields_order'] as $item ) {
                    if ( ! in_array( $item, $settings['details_fields_order'], true ) ) {
                        $settings['details_fields_order'][] = $item;
                    }
                }
            }
            foreach ( $settings as $key => $value ) {
                if ( $key !== 'l10n' ) {
                    $appearance[ $key ] = $value;
                }
            }
            if ( isset( $settings['show_address'] ) && $settings['show_address'] ) {
                $appearance['details_fields_show'][] = 'address';
            }
            if ( isset( $settings['show_notes'] ) && $settings['show_notes'] ) {
                $appearance['details_fields_show'][] = 'notes';
            }
            if ( isset( $settings['show_terms'] ) && $settings['show_terms'] ) {
                $appearance['details_fields_show'][] = 'terms';
            }

            $appearance['custom_css'] = $db_appearance['custom_css'];
            $appearance['token'] = $db_appearance['token'];

            self::translateL10n( isset( $settings['l10n'] ) ? $settings['l10n'] : array(), $appearance['l10n'] );
        }

        // Saved settings may contain flags of add-ons that are no longer active.
        if ( ! empty( $appearance['chain_enabled'] ) && ! Lib\Config::chainAppointmentsActive() ) {
            $appearance['chain_enabled'] = false;
        }
        if ( ! empty( $appearance['multiply_enabled'] ) && ! Lib\Config::multiplyAppointmentsActive() ) {
            $appearance['multiply_enabled'] = false;
        }
        if ( isset( $appearance['multiply_max_quantity'] ) ) {
            $appearance['multiply_max_quantity'] = (int) get_option( 'bookly_multiply_appointments_quantity_max', 10 );
        }

        return $appearance;
    }

    /**
     * @return array
     */
    protected static function getAiAssistantDefaults()
    {
        return array(
            'main_color' => '#F4662F',
            // Customer bubble follows the widget's main color until it is set
            // explicitly (null keeps the CSS fallback in Bubble.svelte alive),
            // the assistant bubble is the neutral surface the theme ships with.
            'question_bubble_color' => null,
            'answer_bubble_color' => '#F1F5F9',
            'display_mode' => 'floating',
            'l10n' => array(
                'title'        => __( 'Chat with us', 'bookly-responsive-appointment-booking-tool' ),
                'onlineStatus' => __( 'Online now', 'bookly-responsive-appointment-booking-tool' ),
                'placeholder'  => __( 'Message', 'bookly-responsive-appointment-booking-tool' ),
                'greeting'     => __( 'Hi there!', 'bookly-responsive-appointment-booking-tool' ) . ' 👋',
                'emptyState'   => __( 'Ask about services, availability, or book an appointment right here.', 'bookly-responsive-appointment-booking-tool' ),
                'openChat'     => __( 'Open chat', 'bookly-responsive-appointment-booking-tool' ),
                'closeChat'    => __( 'Close chat', 'bookly-responsive-appointment-booking-tool' ),
                'resetChat'    => __( 'Reset chat', 'bookly-responsive-appointment-booking-tool' ),
                'send'         => __( 'Send', 'bookly-responsive-appointment-booking-tool' ),
                'error'        => __( 'Something went wrong. Please try again.', 'bookly-responsive-appointment-booking-tool' ),
                'errorQuotaExceeded' => __( 'Our chat assistant is temporarily unavailable. Please try again later, or contact us directly to book your appointment.', 'bookly-responsive-appointment-booking-tool' ),
            ),
        );
    }

    /**
     * Readable text color for a user-picked background — the AI assistant lets
     * the site owner color the chat bubbles, and a fixed white (or a fixed
     * dark) caption would go unreadable on half of the palette. WCAG relative
     * luminance, same math the admin preview uses (AppearanceAiAssistant.svelte).
     *
     * @param string $hex Background color, #rgb or #rrggbb.
     * @return string
     */
    public static function contrastTextColor( $hex )
    {
        $light = '#FFFFFF';
        $dark = '#0F172A';
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
            return $light;
        }

        $channels = array();
        foreach ( array( 0, 2, 4 ) as $offset ) {
            $c = hexdec( substr( $hex, $offset, 2 ) ) / 255;
            $channels[] = $c <= 0.04045 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
        }
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $luminance > 0.45 ? $dark : $light;
    }

    /**
     * @param array $strings
     * @param array $l10n
     */
    protected static function translateL10n( $strings, &$l10n )
    {
        foreach ( $strings as $key => $text ) {
            if ( is_array( $text ) ) {
                self::translateL10n( $text, $l10n[ $key ] );
            } else {
                $l10n[ $key ] = Lib\Utils\Common::getTranslatedString( 'appearance_string_' . md5( $text ), $text );
            }
        }
    }
}
