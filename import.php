<?
    // ========================================================================================================
    function import_render() {
    // ========================================================================================================
        $action = '?' . http_build_query(array('mode' => MODE_PLUGIN, PLUGIN_PARAM_FUNC => 'import_render'));
        if($_SESSION['user_id'] == 1)
            $imp_but = '<button data-proc="execute" type="button" class="form-control btn btn-default">Import!</button>';
        else
            $imp_but = false;
        echo <<<HTML
            <style>
                table {
                    /*font: 15px Courier, sans-serif;*/
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
                    background-color: #ffccff;
                    font-weight: bold;
                }
                .found-id {
                    background-color: #66ff66;
                    font-weight: bold;
                }
                .person-group {
                    background-color: #99ccff;
                    font-weight: bold;
                }
            </style>
            <form class='form-inline'>
                <button data-proc="test_dates" type="button" class="form-control btn btn-default">Datumsangaben analysieren</button>
                <button data-proc="test_persons" type="button" class="form-control btn btn-default">Personennamen analysieren</button>
                <button data-proc="test_documents" type="button" class="form-control btn btn-default">Dokumente analysieren</button>
                <button data-proc="test_run" type="button" class="form-control btn btn-default">Import-Testlauf</button>
                $imp_but
            </form>
            <hr />
            <script>
                $(document).ready(function() {
                    $('button').click(function() {
                        location.href = "$action&proc=" + $(this).data('proc');
                    });
                });
            </script>
HTML;
        if(isset($_GET['proc']) && function_exists('import_' . $_GET['proc']))
            call_user_func('import_' . $_GET['proc']);
    }

    function ends_with($end, $s) {
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
			$text
		);
	}

    // ========================================================================================================
    function get_all_persons_from_db($db) {
    // ========================================================================================================
        $persons = array();
        $sql = 'select id, dmg_plain(lastname_translit) ln, dmg_plain(forename_translit) fn, dmg_plain(byname_translit) bn from persons';
        foreach($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $ln = $row['ln'];
            if(ends_with('al-', $ln))
                $ln = 'al-' . mb_substr($ln, 0, -3);
            $full_name = preg_replace('/[^a-z]/', '', $row['fn'] . $row['bn'] . $ln);
            $persons[$full_name] = $row['id'];
        }
        return $persons;
    }

    // ========================================================================================================
    function get_db_person_groups($db) {
    // ========================================================================================================
        $sql = <<<SQL
        select id, group_name, left(family_name, strpos(family_name, ',') - 1) family_name
        from (
        select g.id, g.name_translit group_name, (
            select lastname_translit from person_of_group pg, persons p
            where g.id = pg.person_group
            and pg.person = p.id
            limit 1
        ) family_name
        from person_groups g
        ) x
        where family_name is not null
        and family_name <> 'Unknown'
SQL;
        $groups = array();
        foreach($db->query($sql, PDO::FETCH_ASSOC) as $row)
            $groups[$row['family_name']] = $row;
        return $groups;
    }

    // ========================================================================================================
    /*
        TODO FRAGEN:
        - Vorder/Rückseite richtig interpretiert vD1-20 heißt: Vorderseite von D1-20??
    */
    function import_test_documents() {
    // ========================================================================================================
        echo <<<HTML
            <h2>Dokumente</h2>
            <table class='table table-striped table-bordered table-responsive table-condensed'>
                <tr>
                    <th class=''>#</th>
                    <th class=''>Nr</th>
                    <th class=''>Sig.</th>
                    <th class=''>Bündel</th>
                    <th class=''>Aufnahme</th>
                    <th class=''>dif</th>
                    <th class=''>Typ</th>
                    <th class=''>Rückseite?</th>
                    <th class=''>DB-Signatur</th>
                    <th class=''>DB-ID</th>
                    <th class=''>Relevant?</th>
                </tr>
HTML;
        $db = db_connect();
        foreach($db->query('select id, signature from documents', PDO::FETCH_ASSOC) as $row)
            $db_docs[$row['signature']] = $row['id'];
        $c_doc = $c_lines = $c_relevant = $c_exist = 0;
        $table = '';
        foreach($db->query('select * from neu', PDO::FETCH_ASSOC) as $doc) {
            $c_lines++;
            $nr = preg_replace('/\s+/', ' ', trim($doc['nr']));
            $dif = preg_replace('/\s+/', ' ', trim($doc['dif']));
            $db_sig = '';
            $match = array();
            $has_sig_aufnahme = false;
            // match "B02-3: 25" or similar
            if(preg_match('/^(?<sig>[BD]\d+)-(?<bundle>[^:]+):\s*(?<aufnahme>.+)$/', $nr, $match)) {
                $bundle = $match['bundle'];
                $has_sig_aufnahme = true;
            }
            else if(preg_match('/^(?<sig>[0123A]\d+)-(?<aufnahme>.+)$/', $nr, $match)) {
                $bundle = '';
                $has_sig_aufnahme = true;
            }

            if($has_sig_aufnahme) {
                $sig = ltrim($match['sig'], '0');
                $aufnahme = ltrim($match['aufnahme'], '0');
                if($pos_slash = strpos($aufnahme, '/')) // e.g. "35/36" -> take only first aufnahme = "35"
                    $aufnahme = substr($aufnahme, 0, $pos_slash);
                $db_sig = sprintf('%s-%s', $sig, $aufnahme);
            }

            if($db_sig == '') {
                echo "<script>console.log('$nr')</script>";
                continue;
            }

            $typ = '&mdash;';
            if(preg_match('/^(?<typ>A|B|MS)/', $dif, $match))
                $typ = $match['typ'];
            $backside = preg_match('/\br(?<frontside>[0123ABD]\d+-\d\:\s?\d+)\b/', $dif, $match);
            $frontside = $backside ? $match['frontside'] : false;
            $db_doc_id = '';
            if(isset($db_docs[$db_sig])) {
                $db_doc_id = $db_docs[$db_sig];
                $c_exist++;
            }
            $relevant =
                // backsides are relevant -- need to be merged with front side
                // !$backside
                // docs already in the DB are irrelevant
                $db_doc_id == ''
                // Wiederholungen are irrelevant
                && !(starts_with('Wiederholung', $dif) || starts_with('Wdh', $dif))
            ;
            if($relevant)
                $c_relevant++;
            $table .= sprintf(
                "<tr>". str_repeat('<td>%s</td>', 11) ."</tr>\n",
                ++$c_doc,
                $nr,
                $sig,
                $bundle,
                $aufnahme,
                $dif,
                $typ,
                $backside ? $frontside : '',
                $db_sig,
                $db_doc_id,
                $relevant ? 'X' : ''
            );
        }
        $info = sprintf("<p>
            <b>%s</b> Zeilen verarbeitet.
            <b>%s</b> identifizierte Dokumente existieren bereits in der DB.
            Nur jene <b>%s</b> Zeilen, in denen die Spalte \"Relevant?\" mit X markiert ist, sind für die Überführung in die DB relevant.
        </p>", $c_lines, $c_exist, $c_relevant);
        echo $info, $table, '</table>', PHP_EOL;
    }

    // ========================================================================================================
    function import_test_persons() {
    // ========================================================================================================
        $db = db_connect();
        $persons_db = get_all_persons_from_db($db);
        $echo = '';

        $query = "select distinct (adressat) person from neu union
            select distinct absender from neu union
            select distinct weitere from neu";
        //$query .= " limit 1000";
        $table = <<<TABLE
            <table class='table table-striped table-bordered table-responsive table-condensed'>
                <tr>
                    <th class='col-md-1'>#</th>
                    <th class='col-md-5'>Originaltext</th>
                    <th class='col-md-6'>Aufgetrennt</th>
                    <!--<th>Personen in DB</th>
                    <th>Neue Personen</th>
                    <th>Jahr orig.</th>
                    <th>Processed</th>
                    <th>DMG Plain</th>-->
                </tr>
TABLE;
        $c = 1;
        $arr_fullnames = array();
        $arr_names = array();
        $c_found = 0;
        $new_names = array();
        $mentioned_groups = array();
        $person_groups = get_db_person_groups($db);

        foreach($db->query($query, PDO::FETCH_ASSOC) as $row) {
            $orig = unify_diacritics(trim($row['person']));
            if($orig == '')
                continue;

            $pdb = $pn = '';

            $pers = $orig;

            // remove parentheses/brackets and contained text
            $pers = preg_replace('/\[.*?\]/', '', $pers);
            $pers = preg_replace('/\(.*?\)/', '', $pers);

            // remove "seine frau"
            $pers = str_ireplace('seine frau', '', $pers);

            // general replacements of Names
            $pers = preg_replace(
                array('/(*UTF8)\bqais\b/i', '/(*UTF8)\bsaif\b/i', '/(*UTF8)\bfaiṣal\b/i', '/(*UTF8)\bsulaimān\b/i', '/(*UTF8)\bsamīḥ\b/i'),
                array('Qays', 'Sayf', 'Fayṣal', 'Sulaymān', 'Samḥ'),
                $pers);

            $pers = trim(preg_replace('/\s+/', ' ', $pers));

            // try to split up into multiple person infos
            $pax = preg_split('/[\/,;+]|und/', $pers);
            $split = '';
            // for each person
            foreach($pax as $p) {
                $p = trim($p);

                if(in_array($p, array('Muḥsin')))
                    $p = 'Muḥsin b. Zahrān b. Muḥammad al-ʿAbrī';

                if($p != '' && !isset($arr_names[$p]))
                    $arr_names[$p] = 1;

                // check if person group mentioned
                if(preg_match('/(*UTF8)\balle\b/i', $p, $match)) {
                    $p = trim($p);
                    if(!isset($mentioned_groups[$p]))
                        $mentioned_groups[$p] = 1;
                    else
                        $mentioned_groups[$p]++;
                    $p = " <span class='person-group'>$p</span>";
                }
                // check if last name present (ends with al-X, ar-X, ad-X, etc.)
                else if(preg_match('/(*UTF8)(?<nachname>(?<pre>(a|ā).)\-\s?(?<family>[^\s]+))$/i', $p, $match)) {
                    // make dmg plain
                    $forename = mb_substr($p, 0, mb_strlen($p) - mb_strlen($match['nachname']));
                    $db_search_name = $forename .' al-'.$match['family'];
                    db_get_single_val('select dmg_plain(?)', array($db_search_name), $name_plain, $db);
                    $name_plain = preg_replace('/[^a-z]/', '', $name_plain);

                    if(in_array($name_plain, array('muhsinbzahranbmuhammadalabri')))
                        $name_plain = 'muhsinbzahranbmuhammadbibrahimalabri';

                    if(!isset($arr_fullnames[$name_plain])) {
                        $arr_fullnames[$name_plain] = false;
                        foreach($persons_db as $db_name => $db_id) {
                            if($name_plain == $db_name) {
                                $c_found++;
                                $arr_fullnames[$name_plain] = $db_id;
                                break;
                            }
                        }
                        if(!$arr_fullnames[$name_plain]) {
                            $arr_new_names[] = array(
                                $p,
                                $forename,
                                $match['family'] . ', al-',
                                isset($person_groups[$match['family']]) ? $person_groups[$match['family']] : null
                            );
                        }
                    }

                    $p = "<span class='name-full'>$p</span>";
                    if($arr_fullnames[$name_plain])
                        $p .= " <span class='found-id'>({$arr_fullnames[$name_plain]})</span>";
                }
                $split .= ($p == '' ? '' : "<li>$p</li>");
            }

            $table .= <<<HTML
                <tr>
                    <td>$c</td>
                    <td>$orig</td>
                    <td><ul>$split</ul></td>
                </tr>
HTML;
            $c++;
        }
        $table .= "</table>\n";

        $c_names = count(array_keys($arr_names));
        $c_full = count(array_keys($arr_fullnames));

        $pct = $c_full > 0 ? ' (' . (int) (1000 * $c_found / $c_full) / 10. . '%)' : '';
        $c_new_names = count($arr_new_names);

        echo <<<HTML
            <ul class="nav nav-tabs">
              <li class="active"><a data-toggle="tab" href="#liste">Namen &amp; Personen</a></li>
              <li><a data-toggle="tab" href="#neue">Neue Personen</a></li>
              <li><a data-toggle="tab" href="#gruppen">Personengruppen</a></li>
            </ul>
            <div class="tab-content">
                <div id="liste" class="tab-pane active">
                    <h2>Identifikation von Namen und Personen</h2>
                    <p>
                        Es sind $c_names verschiedene Namensangaben in der Originaltabelle.
                        Davon konnten $c_full vollständige Namen identifiziert werden.
                        Von diesen vollständigen Namen wurden $c_found in der DB gefunden$pct.
                    </p>
                    <p>Legende:
                        <span class="name-full">Vollständiger Personenname</span>
                        <span class="found-id">ID der gefundenen Person in der DB</span>
                        <span class="person-group">Personengruppe</span>
                    </p>
                    $table
                </div>
                <div id="neue" class="tab-pane">
                    <h2>Neu zu erstellende Personendatensätze</h2>
                    <p>Folgende $c_new_names als vollständig erkannte Namensangeben wurden in der DB nicht gefunden. Diese würden als neue Personen mit Vorname + Familienname wie folgt in der DB angelegt werden:</p>
                    <table class="table table-striped table-bordered table-responsive table-condensed">
                        <tr>
                            <th>#</th>
                            <th>Namensangabe aus der Tabelle</th>
                            <th>Vorname</th>
                            <th>Familienname</th>
                            <th>Personengruppe in DB</th>
                        </tr>
HTML;
        $c = 1;
        foreach($arr_new_names as $new_name) {
            $group = '';
            if(is_array($new_name[3]))
                $group = $new_name[3]['group_name'] . ' (' . $new_name[3]['id'] . ')';
            echo sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                $c++, $new_name[0], $new_name[1], $new_name[2], $group
            );
        }
        echo <<<HTML
                </table>
HTML;

        // PERSON GROUPS
        $groups = '';
        uasort($mentioned_groups, function($a, $b) {
            return $a < $b ? 1 : -1;
        });
        $g_tot = array_sum($mentioned_groups);
        foreach($mentioned_groups as $g => $c) {
            $groups .= "<li>$g [{$c}x]</li>\n";
        }

        echo <<<HTML
            </div>
            <div id="gruppen" class="tab-pane">
                <h2>Genannte Personengruppen</h2>
                <p>Es gibt $g_tot Nennungen, die Gruppen von Personen referenzieren:</p>
                <ol>
                    $groups
                </ol>
            </div>
        </div>
HTML;
    }

    // ========================================================================================================
    // QUESTIONS:
    // - Wenn nur "Rabi" steht, ist das dann immer Rabi I ? Gleiches für "Gumada"
    function import_test_dates() {
    // ========================================================================================================
        $db = db_connect();
        //$query = "select nr, jahr, datum orig, replace(dmg_plain(datum), E'\011', ' ') plain from neu";
        $query = "select jahr, datum orig, replace(dmg_plain(datum), E'\011', ' ') plain from neu group by 1,2";
        echo <<<TABLE
            <h2>Analyse der Datumsangaben</h2>
            <p>Die ersten beiden Spalten zeigen alle unterschiedlichen und nichtleeren Jahr- und Datumsspalten der Altdaten-Tabelle. Die drei letzten Spalten Tag, Monat und Jahr entsprechen den aus den ersten beiden Spalten extrahierten Datumsbestandteilen.</p>
            <p>Folgende Annahme wurde getroffen: Wenn nur "Rabīʿ" (ohne I oder II) als Monat gefunden wurde, dann wurde "Rabīʿ I" angenommen. Gleiches gilt für Ǧumādā.</p>
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
        // names that contain other names should be ordered accordingly: e.g. first rabi ii, then rabi i !!
        $arab_months = array(
            'muharram' => 1,
            'safar' => 2,
            'rabi ii' => 4,
            'rabi i' => 3,
            'gumada ii' => 6,
            'gumada i' => 5,
            'ragab' => 7,
            'saban' => 8,
            'ramadan' => 9,
            'sawwal' => 10,
            'du lqada' => 11,
            'du lhigga' => 12,

            // exceptions
            'r. ii' => 4,
            'r. i' => 3,
            'rabi' => 3,
            'rabii ii' => 4,
            'g ii' => 6,
            'g i' => 5,
            'gumada' => 5,
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

    require_once 'import_run.php';
?>
