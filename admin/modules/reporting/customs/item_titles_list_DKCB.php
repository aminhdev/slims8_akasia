<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Item List */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-reporting');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MDLBS.'reporting/report_dbgrid.inc.php';

$page_title = 'Items/Copies Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
?>
    <!-- filter -->
    <fieldset>
        <div class="per_title">
            <h2><?php echo __('Items Title List'); ?></h2>
        </div>
        <div class="infoBox">
            <?php echo __('Report Filter'); ?>
        </div>
        <div class="sub_section">
            <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
                <div id="filterForm">
                    <div class="divRow">
                        <div class="divRowLabel"><?php echo __('Location'); ?></div>
                        <div class="divRowContent">
                            <?php
                                $loc_q = $dbs->query('SELECT location_id, location_name FROM mst_location');
                                $loc_options = array();
                                // $loc_options[] = array('0', __('ALL'));
                                while ($loc_d = $loc_q->fetch_row()) {
                                    $loc_options[] = array($loc_d[0], $loc_d[0]);
                                }
                                echo simbio_form_element::selectList('location', $loc_options);
                            ?>
                        </div>
                    </div>
                    <div class="divRow">
                        <div class="divRowLabel"><?php echo 'Độ dài chữ số'; ?></div>
                        <div class="divRowContent">
                            <?php
                                $itemCodeLen = array(
                                    [4, 4],
                                    [5, 5],
                                    [6, 6],
                                    [7, 7],
                                    [8, 8]
                                );
                                echo simbio_form_element::selectList('itemCodeLen', $itemCodeLen);
                            ?>
                        </div>
                    </div>
                    <div class="divRow">
                        <div class="divRowLabel"><?php echo 'Number Start'; ?></div>
                        <div class="divRowContent">
                        <?php echo simbio_form_element::textField('text', 'itemCodeStart', '', 'style="width: 50%"'); ?>
                        </div>
                    </div>

                    <div class="divRow">
                        <div class="divRowLabel"><?php echo __('Record each page'); ?></div>
                        <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo __('Set between 20 and 200'); ?></div>
                    </div>
                </div>
                <div style="padding-top: 10px; clear: both;">
                    <input type="button" name="moreFilter" value="<?php echo __('Show More Filter Options'); ?>" />
                    <input type="submit" name="applyFilter" value="<?php echo __('Apply Filter'); ?>" />
                    <input type="hidden" name="reportView" value="true" />
                </div>
            </form>
        </div>
    </fieldset>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} else {
    ob_start();
    // table spec
    $table_spec = 'item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        LEFT JOIN mst_publisher AS p ON b.publisher_id = p.publisher_id
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn(
        'DATE_FORMAT(i.input_date, \'%d/%m/%Y\') AS \''.__('Input Dater').'\'',
        'i.item_code AS \''.__('Item Coder').'\'',
        'b.title AS \''.__('Title/Author').'\'',
        '"" AS \''.'Kiem ke'.'\'',
        '"" AS \''.'Kiem ke'.'\'',
        '"" AS \''.'Kiem ke'.'\'',
        'p.publisher_name AS \''.__('Publisher Name').'\'',
        'b.publish_year AS \''.__('Publisher Year').'\'',
        'b.isbn_issn AS \''.__('Price').'\'',
        'b.call_number AS \''.__('Call Numbe').'\'', 'i.biblio_id'
    );

    $reportgrid->setSQLorder('b.title ASC');

    // is there any search
    $criteria = 'b.biblio_id IS NOT NULL ';
    if (isset($_GET['title']) AND !empty($_GET['title'])) {
        $keyword = $dbs->escape_string(trim($_GET['title']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' AND (';
            foreach ($words as $word) {
                $concat_sql .= " (b.title LIKE '%$word%' OR b.isbn_issn LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        } else {
            $criteria .= ' AND (b.title LIKE \'%'.$keyword.'%\' OR b.isbn_issn LIKE \'%'.$keyword.'%\')';
        }
    }
    if (isset($_GET['itemCodeStart']) AND !empty($_GET['itemCodeStart'])) {
        $location           = $_GET['location'];
        $itemCodeStart      = $_GET['itemCodeStart'];
        $itemCodeLen        = $_GET['itemCodeLen'];
        $eachPage           = $_GET['recsEachPage'];
        $listItemCodeSearch = [];

        for ($i = (int)$itemCodeStart; $i <= (int)$eachPage; $i++) {
            $listItemCodeSearch[] = $location.sprintf("%0{$itemCodeLen}d", $i);
        }

        $listItemCodeSearch = implode("','", $listItemCodeSearch);
        $criteria .= " AND i.item_code IN('".$listItemCodeSearch."')";

    }
    if (isset($_GET['collType'])) {
        $coll_type_IDs = '';
        foreach ($_GET['collType'] as $id) {
            $id = (integer)$id;
            if ($id) {
                $coll_type_IDs .= "$id,";
            }
        }
        $coll_type_IDs = substr_replace($coll_type_IDs, '', -1);
        if ($coll_type_IDs) {
            $criteria .= " AND i.coll_type_id IN($coll_type_IDs)";
        }
    }
    if (isset($_GET['gmd']) AND !empty($_GET['gmd'])) {
        $gmd_IDs = '';
        foreach ($_GET['gmd'] as $id) {
            $id = (integer)$id;
            if ($id) {
                $gmd_IDs .= "$id,";
            }
        }
        $gmd_IDs = substr_replace($gmd_IDs, '', -1);
        if ($gmd_IDs) {
            $criteria .= " AND b.gmd_id IN($gmd_IDs)";
        }
    }
    if (isset($_GET['status']) AND $_GET['status']!='0') {
        $status = $dbs->escape_string(trim($_GET['status']));
        $criteria .= ' AND i.item_status_id=\''.$status.'\'';
    }
    if (isset($_GET['class']) AND ($_GET['class'] != '')) {
        $class = $dbs->escape_string($_GET['class']);
        $criteria .= ' AND b.classification LIKE \''.$class.'%\'';
    }
    if (isset($_GET['location']) AND !empty($_GET['location'])) {
        $location = $dbs->escape_string(trim($_GET['location']));
        $criteria .= ' AND i.item_code LIKE \'%'.$location.'%\'';
    }
    if (isset($_GET['publishYear']) AND !empty($_GET['publishYear'])) {
        $publish_year = $dbs->escape_string(trim($_GET['publishYear']));
        $criteria .= ' AND b.publish_year LIKE \'%'.$publish_year.'%\'';
    }
    if (isset($_GET['itemID']) AND !empty($_GET['itemID'])) {
        $item_id = $_GET['itemID'];
        $criteria .= ' AND i.item_id >= '.$item_id;
    }
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }

    $reportgrid->setSQLCriteria($criteria);

    // callback function to show title and authors
    function showTitleAuthors($obj_db, $array_data)
    {
        if (!$array_data[10]) {
            return;
        }
        // author name query
        $_biblio_q = $obj_db->query('SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[10]);
        $_authors = '';

        while ($_biblio_d = $_biblio_q->fetch_row()) {
            $_title = $_biblio_d[0];
            $_authors .= $_biblio_d[1].' - ';
        }
        $_authors = substr_replace($_authors, '', -3);
        $_output = $_title.'<br /><i>'.$_authors.'</i>'."\n";
        return $_output;
    }

    function showStatus($obj_db, $array_data)
    {
        $q = $obj_db->query('SELECT item_status_name FROM mst_item_status WHERE item_status_id=\''.$array_data[3].'\'');
        $d = $q->fetch_row();
        $s = $d[0];
        $output = $s;

        if (!$s) {
            $output = __('Available');
        }

        return $output;
    }
    // modify column value
    $reportgrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
    // $reportgrid->modifyColumnContent(3, 'callback{showStatus}');
    $reportgrid->invisible_fields = array(10);

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'#pagingBox\').html(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SB.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
