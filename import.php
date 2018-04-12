<?
    // ========================================================================================================
    function import_render() {
    // ========================================================================================================
        $action = '?' . http_build_query(array('mode' => MODE_PLUGIN, PLUGIN_PARAM_FUNC => 'import_render'));
        echo <<<HTML
            <form class='form-inline'>
                <button data-proc="test_dates" type="button" class="form-control btn btn-default">Identify Dates</button>
                <button data-proc="test_persons" type="button" class="form-control btn btn-default">Identify Persons</button>
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
                table.fit {
                    white-space: nowrap;
                    width: 1%;
                }
                .fat {
                    font-weight: bold;
                    color: darkgreen;
                    text-align: center
                }

                /* name markup */
                .name-full {
                    background-color: lightyellow;
                    font-weight: bold;
                }
            </style>
HTML;
        if(isset($_GET['proc']) && function_exists('import_' . $_GET['proc']))
            call_user_func('import_' . $_GET['proc']);
    }

    function ends_with($s, $end) {
        return substr($s, -strlen($end)) == $end;
    }

    // ========================================================================================================
	// called via before_insert_or_update on all tables
	function unify_diacritics($text) {
	// ========================================================================================================
		// if 2-character diacritcs are entered (base char followed by a diacritical mark), these are converted
		// to their corresponding single unicode character

		if(!is_string($text))
			return $text;

		return str_replace(
			array( // base char + diacritics
				'Ā',       'ā',       'Ṯ',       'ṯ',       'Ǧ',       'ǧ',       'Ḥ',       'ḥ',       'Ḏ',       'ḏ',       'Š',       'š',       'Ṣ',       'ṣ',
				'Ḍ',       'ḍ',       'Ṭ',       'ṭ',       'Ẓ',       'ẓ',       'Ġ',       'ġ',       'Ū',       'ū',       'Ī',       'ī',       'S̱',       's̱',
				'Č',       'č',       'Ẕ',       'ẕ',       'Ž',       'ž',       'Ż',       'ż',       'Ç',       'ç',       'Ş',       'ş',       'Ğ',       'ğ',
				'Ḳ',       'ḳ',       'ñ',       'n̡',       'Ė',       'ė'
			),
			array( // single char
				'Ā',       'ā',       'Ṯ',       'ṯ',       'Ǧ',       'ǧ',       'Ḥ',       'ḥ',       'Ḏ',       'ḏ',       'Š',       'š',       'Ṣ',       'ṣ',
				'Ḍ',       'ḍ',       'Ṭ',       'ṭ',       'Ẓ',       'ẓ',       'Ġ',       'ġ',       'Ū',       'ū',       'Ī',       'ī',       'S̱',       's̱',
				'Č',       'č',       'Ẕ',       'ẕ',       'Ž',       'ž',       'Ż',       'ż',       'Ç',       'ç',       'Ş',       'ş',       'Ğ',       'ğ',
				'Ḳ',       'ḳ',       'ñ',       'ŋ',       'Ė',       'ė'
			),
			/*array(
				'A\u0304', 'a\u0304', 'T\u0331', 't\u0331', 'G\u030c', 'g\u030c', 'H\u0323', 'h\u0323', 'D\u0331', 'd\u0331', 'S\u030c', 's\u030c', 'S\u0323', 's\u0323',
				'D\u0323', 'd\u0323', 'T\u0323', 't\u0323', 'Z\u0323', 'z\u0323', 'G\u0307', 'g\u0307', 'U\u0304', 'u\u0304', 'I\u0304', 'i\u0304', 'S\u0331', 's\u0331',
				'C\u030c', 'c\u030c', 'Z\u0331', 'z\u0331', 'Z\u030c', 'z\u030c', 'Z\u0307', 'z\u0307', 'C\u0327', 'c\u0327', 'S\u0327', 's\u0327', 'G\u0306', 'g\u0306',
				'K\u0323', 'k\u0323', 'n\u0303', 'n\u0321', 'E\u0307', 'e\u0307'
			),*/
			$text
		);
	}

    // ========================================================================================================
    function import_test_persons() {
    // ========================================================================================================
        $db = db_connect();
        $query = "select distinct (adressat) person from neu union
            select distinct absender from neu union
            select distinct weitere from neu limit 1000";
        echo <<<TABLE
            <p>Legende: <span class="name-full">Kompletter Name</span></p>
            <table class='table table-striped table-bordered table-responsive table-condensed'>
                <tr>
                    <th>#</th>
                    <th>Originaltext</th>
                    <th>Split</th>
                    <!--<th>Personen in DB</th>
                    <th>Neue Personen</th>
                    <th>Jahr orig.</th>
                    <th>Processed</th>
                    <th>DMG Plain</th>-->
                </tr>
TABLE;
        $c = 1;
        foreach($db->query($query, PDO::FETCH_ASSOC) as $row) {
            $orig = unify_diacritics(trim($row['person']));
            if($orig == '')
                continue;

            $pdb = $pn = '';

            // remove parentheses/brackets and contained text
            $pers = $orig;
            $pers = preg_replace('/\[.*?\]/', '', $pers);
            $pers = preg_replace('/\(.*?\)/', '', $pers);
            $pers = trim(preg_replace('/\s+/', ' ', $pers));

            // try to split up into multiple person infos
            $pax = preg_split('/[\/,;+]|und/', $pers);
            $split = '';
            // for each person
            foreach($pax as $p) {
                $p = trim($p);

                // check if last name present (ends with al-X, ar-X, ad-X, etc.)
                if(preg_match('/(*UTF8)(?<nachname>(a|ā).\-\s?.+)$/i', $p, $match))
                    $p = "<span class='name-full'>$p</span>";

                // TODO split into firstname / lastname

                $split .= ($p == '' ? '' : "<li>$p</li>");
            }

            echo <<<HTML
                <tr>
                    <td class="code">$c</td>
                    <td class="code">$orig</td>
                    <td class="code"><ul>$split</ul></td>
                    <!--<td class="code">$pdb</td>
                    <td class="code">$pn</td>-->
                </tr>
HTML;
            $c++;
        }
        echo "</table>\n";
    }

    // ========================================================================================================
    // QUESTIONS:
    // - Wenn nur "Rabi" steht, ist das dann immer Rabi I ? Gleiches für "Gumada"
    function import_test_dates() {
    // ========================================================================================================
        $db = db_connect();
        $query = "select nr, jahr, datum orig, replace(dmg_plain(datum), E'\011', ' ') plain from neu";
        echo <<<TABLE
            <table class='table fit rtable-striped table-bordered table-responsive table-condensed'>
                <tr>
                    <th>Jahr</th>
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
            'rabi' => 3, 'r. i' => 3, 'r. ii' => 4,
            'rabii ii' => 4,
            'gumada' => 5, 'g i' => 5,
            'g ii' => 6,
            'sauwal' => 10, 'sawal' => 10,
            'alqada' => 11, 'al qada' => 11,
            'alhagg' => 12, 'alhag' => 12, 'al hagg' => 12, 'al hag' => 12,
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
            // remove everything non numeric or dot or blank from jahr
            $jahr = trim(preg_replace('/[^0-9\. ]/', '', $jahr));

            // now we can extract the jahr
            if(!preg_match('/^\d\d\d\d\d*$/', $jahr))
                $jahr = '';
            else
                $jahr = substr($jahr, 0, 4);

            $tag = $monat = '';
            // recognize arab months, and remove recognized month
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

            if($col_orig == '') {
                $col_orig = '&nbsp;';
                if($col_jahr == '')
                    continue;
            }

            // -----------------------------------------------------------------
            echo <<<HTML
                <tr>
                    <td class="code">$col_jahr</td>
                    <td class="code">$col_orig</td>
                    <td class="code fat">$tag</td>
                    <td class="code fat">$monat</td>
                    <td class="code fat">$jahr</td>
                    <!--<td class="annot">$col_jahr</td>
                    <td class="annot">$d</td>
                    <td class="annot">$col_plain</td>-->
                </tr>
HTML;
        }
        echo "</table>\n";
    }
?>
