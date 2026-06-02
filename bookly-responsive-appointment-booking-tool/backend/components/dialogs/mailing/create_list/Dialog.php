<?php
namespace Bookly\Backend\Components\Dialogs\Mailing\CreateList;

use Bookly\Lib;
use Bookly\Backend\Components\Controls\Buttons;

class Dialog extends Lib\Base\Component
{
    /**
     * Render create mailing list dialog.
     */
    public static function render()
    {
        self::enqueueStyles( array(
            'backend' => array( 'css/fontawesome-all.min.css' => array( 'bookly-backend-globals' ), ),
        ) );

        self::enqueueScripts( array(
            'module' => array( 'js/create-mailing-list-dialog.js' => array( 'bookly-backend-globals' ), ),
        ) );

        self::renderTemplate( 'dialog' );
    }
}