<?php
namespace Bookly\Backend\Components\Dialogs\BookingWizard\Proxy;

use Bookly\Lib;

/**
 * @method static array prepareL10n( array $l10n ) Add strings owned by add-on features to the booking wizard l10n (each add-on uses its own text domain).
 * @method static array prepareCatalog( array $catalog ) Enrich the booking wizard catalog with add-on-owned data (e.g. staff location bindings).
 */
abstract class Shared extends Lib\Base\Proxy
{

}
