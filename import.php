<?
    // ========================================================================================================
    function import_render() {
    // ========================================================================================================
        $action = '?' . http_build_query(array('mode' => MODE_PLUGIN, PLUGIN_PARAM_FUNC => 'import_render'));
        echo <<<HTML
            <form class='form-inline'>
                <button data-proc="test_dates" type="button" class="form-control btn btn-default">Identify Dates</button>
            </form>
            <hr />
            <script>
                $(document).ready(function() {
                    $('button').click(function() {
                        location.href = "$action&proc=" + $(this).data('proc');
                    });
                });
            </script>
            <style>
                .code {
                    font: 15px Courier, sans-serif;
                }
                .table {
                    white-space: nowrap;
                    width: 1%;
                }
            </style>
HTML;
        if(isset($_GET['proc']) && function_exists('import_' . $_GET['proc']))
            call_user_func('import_' . $_GET['proc']);
    }

    // ========================================================================================================
    // QUESTIONS:
    // - Wenn nur "Rabi" steht, ist das dann immer Rabi I ? Gleiches für "Gumada"
    function import_test_dates() {
    // ========================================================================================================
        $db = db_connect();
        $query = "select nr, jahr, datum orig, replace(dmg_plain(datum), E'\011', ' ') plain from neu";
        echo <<<TABLE
            <table class='table table-striped table-bordered table-responsive table-condensed'>
                <tr>
                    <th>Datumstext</th>
                    <th>Tag</th>
                    <th>Monat</th>
                    <th>Jahr</th>
                    <!--<th>Jahr orig.</th>
                    <th>Processed</th>
                    <th>DMG Plain</th>-->
                </tr>
TABLE;
        $arab_months = array(
            'muharram' => 1,
            'safar' => 2,
            'rabi i' => 3,
            'rabi ii' => 4,
            'gumada i' => 5,
            'gumada ii' => 6,
            'ragab' => 7,
            'saban' => 8,
            'ramadan' => 9,
            'sawwal' => 10,
            'du lqada' => 11,
            'du lhigga' => 12,

            // exceptions
            'rabi' => 3, #???
            'r. i' => 3,
            'r. ii' => 4,
            'rabii ii' => 4,
            'gumada' => 5, #???
            'g i' => 5,
            'g ii' => 6,
            'sauwal' => 10,
            'sawal' => 10,
            'alqada' => 11,
            'al qada' => 11,
            'alhagg' => 12,
            'alhag' => 12,
            'al hagg' => 12,
            'al hag' => 12,
        );

        foreach($db->query($query, PDO::FETCH_ASSOC) as $row) {
            $col_orig = $row['orig'];
            $col_plain = $d = $row['plain'];
            $col_jahr = $jahr = $row['jahr'];

            // remove squared brackets and everything inside and directly adjacent
            $d = trim(preg_replace('/[^\s]*\[.*?\][^\s]*/', '', $d));
            $jahr = trim(preg_replace('/[^\s]*\[.*?\][^\s]*/', '', $jahr));

            // remove parentheses and everything inside and directly adjacent
            $d = trim(preg_replace('/[^\s]*\(.*?\)[^\s]*/', '', $d));
            $jahr = trim(preg_replace('/[^\s]*\(.*?\)[^\s]*/', '', $jahr));

            // remove everything non alpha numeric from datum
            $d = trim(preg_replace('/[^a-z0-9\. ]/', '', $d));
            // remove everything non numeric from datum
            $jahr = trim(preg_replace('/[^0-9\. ]/', '', $jahr));

            if(!preg_match('/^\d\d\d\d\d*$/', $jahr))
                $jahr = '';
            else
                $jahr = substr($jahr, 0, 4);

            $tag = $monat = '';
            // recognize arab months
            foreach($arab_months as $name => $m) {
                $repl = str_replace($name, '', $d);
                if($d !== str_replace($name, '', $d)) {
                    $monat = $m;
                    $d = $repl;
                    break;
                }
            }

            // remove any non-numeric or blank character remaining
            $d = trim(preg_replace('/^0+|[^0-9 ]/', '', $d));
            // extract day and year
            if(preg_match('/^\d\d?$/', $d))
                $tag = $d;
            else if(preg_match('/^\d\d\d\d$/', $d))
                $jahr = $d;
            else if(preg_match('/^\d\d?\s*\d\d\d\d$/', $d)) {
                $jahr = substr($d, -4);
                $tag = substr($d, 0, strpos($d, ' '));
            }

            if($col_orig == '')
                $col_orig = '<i>(empty)</i>';

            // -----------------------------------------------------------------
            echo <<<HTML
                <tr>
                    <td class="code">$col_orig</td>
                    <td class="code">$tag</td>
                    <td class="code">$monat</td>
                    <td class="code">$jahr</td>
                    <!--<td class="annot">$col_jahr</td>
                    <td class="annot">$d</td>
                    <td class="annot">$col_plain</td>-->
                </tr>
HTML;
        }
        echo "</table>\n";
    }
?>
