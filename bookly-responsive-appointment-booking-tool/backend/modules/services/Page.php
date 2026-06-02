<?php
namespace Bookly\Backend\Modules\Services;

use Bookly\Lib;

class Page extends Lib\Base\Ajax
{
    /**
     * Render page.
     */
    public static function render()
    {
        wp_enqueue_media();
        self::enqueueStyles( array(
            'wp' => array( 'wp-color-picker' ),
            'alias' => array( 'bookly-backend-globals' ),
            'backend' => array( 'tailwind/tailwind.css' ),
        ) );

        self::enqueueScripts( array(
            'wp' => array( 'wp-color-picker' ),
            'backend' => array(
                'js/range-tools.js' => array( 'bookly-backend-globals' ),
                'js/sortable.min.js',
                'js/bookly-datatables.js' => array( 'bookly-backend-globals' ),
            ),
            'module' => array( 'js/services-list.js' => array( 'bookly-range-tools.js' ) ),
        ) );

        $categories = Lib\Entities\Category::query()->sortBy( 'position' )->fetchArray();
        foreach ( $categories as &$category ) {
            $category['attachment'] = Lib\Utils\Common::getAttachmentUrl( $category['attachment_id'], 'thumbnail' ) ?: null;
        }

        $datatables = Lib\Utils\Tables::getSettings( Lib\Utils\Tables::SERVICES );

        wp_localize_script( 'bookly-services-list.js', 'BooklyL10n', array(
            'are_you_sure' => esc_attr__( 'Are you sure?', 'bookly' ),
            'appointmentsUrl' => Lib\Utils\Common::escAdminUrl( \Bookly\Backend\Modules\Appointments\Ajax::pageSlug() ),
            'private_warning' => esc_attr__( 'The service will be created with the visibility of Private.', 'bookly' ),
            'edit' => esc_attr__( 'Edit', 'bookly' ) . '…',
            'duplicate' => esc_attr__( 'Duplicate', 'bookly' ) . '…',
            'reorder' => esc_attr_x( 'Reorder', 'order of elements', 'bookly' ),
            'categories' => $categories,
            'uncategorized' => esc_attr__( 'Uncategorized', 'bookly' ),
            'noResultFound' => esc_attr__( 'No results found', 'bookly' ),
            'zeroRecords' => __( 'No matching records found', 'bookly' ),
            'processing' => esc_attr__( 'Processing', 'bookly' ) . '…',
            'emptyTable' => __( 'No data available in table', 'bookly' ),
            'show_type' => count( Proxy\Shared::prepareServiceTypes( array() ) ) > 0,
            'new_service' => __( 'New service', 'bookly' ) . '…',
            'search' => __( 'Quick search by title, category', 'bookly' ) . '…',
            'delete' => __( 'Delete', 'bookly' ) . '…',
            'order' => _x( 'Order', 'drag to reorder', 'bookly' ) . '…',
            'tags' => __( 'Tags', 'bookly' ) . '…',
            'manage_categories' => __( 'Categories', 'bookly' ) . '…',
            'filters' => array(
                'category' => __( 'Category', 'bookly' ),
                'searchPlaceholder' => __( 'Search', 'bookly' ) . '…',
            ),
            'rowsPerPage' => __( 'Rows per page', 'bookly' ),
            'proEnabled' => Lib\Config::proActive(),
            'datatables' => $datatables,
        ) );

        $data['categories'] = $categories;
        $data['datatable'] = $datatables[ Lib\Utils\Tables::SERVICES ];

        self::renderTemplate( 'index', $data );
    }

    /**
     * Get data for staff drop-down.
     *
     * @return array
     */
    public static function getStaffDropDownData()
    {
        if ( Lib\Config::proActive() ) {
            return Lib\Proxy\Pro::getStaffDataForDropDown();
        } else {
            $items = Lib\Entities\Staff::query()
                ->select( 'id, full_name' )
                ->whereNot( 'visibility', 'archive' )
                ->sortBy( 'position' )
                ->fetchArray();

            return array(
                0 => array(
                    'name' => '',
                    'items' => $items,
                ),
            );
        }
    }
}