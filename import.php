<?
    // ========================================================================================================
    function import_render() {
    // ========================================================================================================
        $action = '?' . http_build_query(array('mode' => MODE_PLUGIN, PLUGIN_PARAM_FUNC => 'import_render'));
        echo <<<HTML
            <form class='form-inline'>
                <button data-proc="test_dates" type="button" class="form-control btn btn-default">Test Dates</button>
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
                table {

                }
            </style>
HTML;
        if(isset($_GET['proc']) && function_exists('import_' . $_GET['proc']))
            call_user_func('import_' . $_GET['proc']);
    }

    // ========================================================================================================
    function import_test_dates() {
    // ========================================================================================================
        $db = db_connect();
        $query = "select datum orig, replace(dmg_plain(datum), E'\011', ' ') plain from neu where datum not in ('', '---') limit 1000";
        echo <<<TABLE
            <table class='table table-striped table-bordered table-responsive'>
                <tr>
                    <th>Original</th>
                    <th>Processed</th>
                    <th>DMG Plain</th>
                </tr>
TABLE;
        foreach($db->query($query, PDO::FETCH_ASSOC) as $row) {
            $col_orig = $row['orig'];
            $col_plain = $d = $row['plain'];

            // remove squared brackets and everything inside and directly adjacent
            $d = trim(preg_replace('/[^\s]*\[.*?\][^\s]*/', '', $d));

            // remove parentheses and everything inside and directly adjacent
            $d = trim(preg_replace('/[^\s]*\(.*?\)[^\s]*/', '', $d));

            // remove everything non alpha-numeric, dot or blank
            $d = trim(preg_replace('/[^a-z0-9\. ]/', '', $d));



            // -----------------------------------------------------------------
            echo <<<HTML
                <tr>
                    <td>$col_orig</td>
                    <td>$d</td>
                    <td>$col_plain</td>
                </tr>
HTML;
        }
        echo "</table>\n";
    }
?>
