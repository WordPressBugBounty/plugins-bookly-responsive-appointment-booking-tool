<?php
namespace Bookly\Backend\Components\Gutenberg\Shortcodes;

use Bookly\Lib;

/**
 * Generic Gutenberg picker for any non-classic form type that has saved
 * appearances — core, not Pro-gated, since which types actually exist to
 * show up here already comes from the properly core/Pro-split
 * Lib\Entities\Form::getTypes(): without Pro this ends up with nothing to
 * offer (both $exists_appearance and $forms come back empty for the
 * excluded types below) and simply registers no blocks — harmless no-op,
 * not a reason to gate the whole mechanism behind Pro like it used to be.
 */
class Block extends Lib\Base\Block
{
    /**
     * @inheritDoc
     */
    public static function registerBlockType()
    {
        self::enqueueScripts( array(
            'module' => array(
                'js/shortcodes-block.js' => array( 'wp-blocks', 'wp-components', 'wp-element', 'wp-editor' ),
            ),
        ) );

        // AI assistant is excluded here — it has its own dedicated block
        // (Backend\Components\Gutenberg\BooklyAiAssistant\Block); including
        // it in this generic picker too would just duplicate that with a
        // second, confusing way to insert the same shortcode. Same reason
        // TYPE_BOOKLY_FORM is excluded (its own bookly_form block).
        $excluded_types = array( Lib\Entities\Form::TYPE_BOOKLY_FORM, Lib\Entities\Form::TYPE_AI_ASSISTANT );

        $exists_appearance = array();
        foreach ( Lib\Entities\Form::getTypes() as $type ) {
            if ( ! in_array( $type, $excluded_types, true ) ) {
                $exists_appearance[ $type ] = false;
            }
        }

        $name = __( 'Default', 'bookly-responsive-appointment-booking-tool' );
        $token = '';
        $color = get_option( 'bookly_app_color', '#f4662f' );

        /** @var array $forms */
        $forms = Lib\Entities\Form::query()
            ->select( 'type, name, token, settings' )
            ->sortBy( 'type, name' )
            ->whereNotIn( 'type', $excluded_types )
            ->fetchArray();
        foreach ( $forms as &$form ) {
            $exists_appearance[ $form['type'] ] = true;
            $settings = json_decode( $form['settings'], true );
            $form['color'] = isset( $settings['main_color'] ) ? $settings['main_color'] : $color;
            unset( $form['settings'] );
        }

        $block = array();
        foreach ( $exists_appearance as $type => $exists ) {
            if ( ! $exists ) {
                $forms[] = compact( 'type', 'name', 'token', 'color' );
            }
            $block[ $type ] = array(
                'title' => 'Bookly - ' . Lib\Entities\Form::getTitle( $type ),
                'description' => Lib\Entities\Form::getDescription( $type ),
            );
        }

        wp_localize_script( 'bookly-shortcodes-block.js', 'BooklyShortcodesL10n',
            compact( 'block', 'forms' )
        );
    }
}
