<?
    // ========================================================================================================
    class Tabellenzeile {
    // ========================================================================================================
        public static $alle = array();

        public  $nr = null,
                $dif = null,
                $jahr = null,
                $datum = null,
                $adressat = null,
                $absender = null,
                $weitere = null,
                $inhalt = null;

        // ----------------------------------------------------------------------------------------------------
        public static function einlesen($row) {
        // ----------------------------------------------------------------------------------------------------
                $z = new Tabellenzeile;
                foreach(array('nr', 'dif', 'jahr', 'adressat', 'absender', 'weitere', 'inhalt') as $prop) {
                    $z->{$prop} = trim($row[$prop]);
                }
                $z->nr = preg_replace('/\s+/', ' ', $z->nr);
                $z->dif = preg_replace('/\s+/', ' ', $z->dif);
                $z->datum = $row['plain_datum'];
                Tabellenzeile::$alle[$z->nr] = $z;
        }
    }

    // ========================================================================================================
    class Aufnahme { // verarbeitete Tabellenzeile
    // ========================================================================================================
        public static $alle = array();

        public static $anz_bereits_existierende = 0;

        public  $signatur = null,
                $buendel = null,
                $datum_jahr = null,
                $datum_monat = null,
                $datum_tag = null,
                $adressat = array(),
                $absender = array(),
                $weitere = array(),
                $notizen = array(),
                $ist_rueckseite = null,
                $nr_kehrseite = null,
                $tabellenzeile = null,
                $typ = null; // verweis auf vor/rückseite

        // ----------------------------------------------------------------------------------------------------
        public static function aus_zeile($z) {
        // ----------------------------------------------------------------------------------------------------
            $a = new Aufnahme;
            $a->tabellenzeile = $z;
            if(!$a->ermittle_signatur())
                return false;

            if(isset(Dokument::$db_docs[$a->signatur])) {
                Aufnahme::$anz_bereits_existierende++;
                return false; // existiert bereits in der DB
            }

            $a->typ = '';
            if(preg_match('/^(?<typ>A|B|MS)/', $z->dif, $match))
                $a->typ = $match['typ'];

            $a->ist_rueckseite = preg_match('/\br(?<frontside>[0123ABD]\d+-\d\:\s?\d+)\b/', $z->dif, $match);
            $a->nr_kehrseite = $a->ist_rueckseite ? $match['frontside'] : null;

            $relevant = !(starts_with('Wiederholung', $z->dif) || starts_with('Wdh', $z->dif));
            if(!$relevant)
                return false;

            Aufnahme::$alle[$z->nr] = $a;

            // Datum einlesen
            $a->datum_ermitteln();

            // Personen einlesen
            $a->personen_ermitteln();

            return true;
        }

        // ----------------------------------------------------------------------------------------------------
        protected function ermittle_signatur() {
        // ----------------------------------------------------------------------------------------------------
            $this->signatur = '';
            $match = array();
            $has_sig_aufnahme = false;
            // match "B02-3: 25" or similar
            if(preg_match('/^(?<sig>[BD]\d+)-(?<bundle>[^:]+):\s*(?<aufnahme>.+)$/', $this->tabellenzeile->nr, $match)) {
                $this->buendel = $match['bundle'];
                $has_sig_aufnahme = true;
            }
            else if(preg_match('/^(?<sig>[0123A]\d+)-(?<aufnahme>.+)$/', $this->tabellenzeile->nr, $match)) {
                $this->buendel = '';
                $has_sig_aufnahme = true;
            }

            if($has_sig_aufnahme) {
                $sig = ltrim($match['sig'], '0');
                $aufnahme = ltrim($match['aufnahme'], '0');
                if($pos_slash = strpos($aufnahme, '/')) // e.g. "35/36" -> take only first aufnahme = "35"
                    $aufnahme = substr($aufnahme, 0, $pos_slash);
                $this->signatur = sprintf('%s-%s', $sig, $aufnahme);
            }
            return $this->signatur != '';
        }

        // ----------------------------------------------------------------------------------------------------
        protected function datum_ermitteln() {
        // ----------------------------------------------------------------------------------------------------
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

            $d = $this->tabellenzeile->datum;
            $this->datum_jahr = $this->tabellenzeile->jahr;

            // remove squared brackets and everything inside and directly adjacent
            $d = trim(preg_replace('/[^\s]*\[.*?\][^\s]*/', '', $d));
            $this->datum_jahr = trim(preg_replace('/[^\s]*\[.*?\][^\s]*/', '', $this->datum_jahr));

            // remove parentheses and everything inside and directly adjacent
            $d = trim(preg_replace('/[^\s]*\(.*?\)[^\s]*/', '', $d));
            $this->datum_jahr = trim(preg_replace('/[^\s]*\(.*?\)[^\s]*/', '', $this->datum_jahr));

            // remove everything non alpha numeric from datum
            $d = trim(preg_replace('/[^a-z0-9\. ]/', '', $d));
            // remove everything non numeric or dot or blank from jahr
            $this->datum_jahr = trim(preg_replace('/[^0-9\. ]/', '', $this->datum_jahr));

            // now we can extract the jahr
            if(!preg_match('/^\d\d\d\d\d*$/', $this->datum_jahr))
                $this->datum_jahr = null;
            else
                $this->datum_jahr = substr($this->datum_jahr, 0, 4);

            // recognize arab months, and remove recognized month
            foreach($arab_months as $name => $m) {
                $repl = str_replace($name, '', $d);
                if($d !== str_replace($name, '', $d)) {
                    $this->datum_monat = $m;
                    $d = $repl;
                    break;
                }
            }

            // remove any non-numeric or blank character remaining
            $d = trim(preg_replace('/^0+|[^0-9 ]/', '', $d));

            // extract day and year
            if(preg_match('/^\d\d?$/', $d))
                $this->datum_tag = $d;
            else if(preg_match('/^\d\d\d\d$/', $d))
                $this->datum_jahr = $d;
            else if(preg_match('/^\d\d?\s*\d\d\d\d$/', $d)) {
                $this->datum_jahr = substr($d, -4);
                $this->datum_tag = substr($d, 0, strpos($d, ' '));
            }
        }

        // ----------------------------------------------------------------------------------------------------
        protected function personen_ermitteln() {
        // ----------------------------------------------------------------------------------------------------
            Person::db_personen_auslesen();
            Personengruppe::db_personengruppen_auslesen();

            $c = 1;
            $arr_fullnames = array();
            $arr_names = array();
            $c_found = 0;
            $new_names = array();
            $mentioned_groups = array();
            //$person_groups = get_db_person_groups($db);

            foreach(array('adressat', 'absender', 'weitere') as $person_feld) {
                $this->{$person_feld} = Person::erzeuge($this->tabellenzeile->{$person_feld});
            }
        }
    }

    // ========================================================================================================
    class Person {
    // ========================================================================================================
        public static $db_persons = array();

        public  $db_id = false,
                $sex = null,
                $vorname = null,
                $familienname = null,
                $beiname = null,
                $vorname_plain = null,
                $familienname_plain = null,
                $beiname_plain = null,
                $familienname_kanon_plain = null, // immer "al-X" statt z.B. "X, al-"
                $vollname_kanon_plain = null,
                $personengruppe = null,
                $notizen = array();

        // ----------------------------------------------------------------------------------------------------
        public static function db_personen_auslesen() {
        // ----------------------------------------------------------------------------------------------------
            Person::$db_persons = array();
            $sql = "
                select id,
                    sex,
                    forename_translit,
                    byname_translit,
                    lastname_translit,
                    dmg_plain(lastname_translit) ln,
                    dmg_plain(forename_translit) fn,
                    dmg_plain(byname_translit) bn
                from persons
            ";
            foreach(Datenbank::$db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $p = new Person;
                $p->id = $row['id'];
                $p->sex = $row['sex'];
                $p->vorname = $row['forename_translit'];
                $p->beiname = $row['byname_translit'];
                $p->familienname = $row['lastname_translit'];
                $p->familienname_plain = $p->familienname_kanon_plain = $row['ln'];
                if(ends_with('al-', $p->familienname_plain))
                    $p->familienname_kanon_plain = 'al-' . mb_substr($p->familienname_plain, 0, -3);
                $p->vollname_kanon_plain = preg_replace('/[^a-z]/', '', $row['fn'] . $row['bn'] . $p->familienname_kanon_plain);
                Person::$db_persons[$p->vollname_kanon_plain] = $p;
            }
        }

        public static $arr_names = array();
        public static $arr_fullnames = array();

        // ----------------------------------------------------------------------------------------------------
        public static function erzeuge($text, $aufnahme) {
        // ----------------------------------------------------------------------------------------------------
            $orig = unify_diacritics(trim($text));
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

            // for each person
            foreach($pax as $p) {
                $pers_obj = new Person;
                $p = trim($p);

                if(in_array($p, array('Muḥsin')))
                    $p = 'Muḥsin b. Zahrān b. Muḥammad al-ʿAbrī';

                if($p != '' && !isset($arr_names[$p]))
                    Person::$arr_names[$p] = 1;

                // check if group of persons mentioned
                if(preg_match('/(*UTF8)\balle\b/i', $p, $match)) {
                    $pers_obj->notizen[] = "Text \"$p\" wurde als Person angelegt, scheint aber eine Gruppe von Personen zu sein";
                    $pers_obj->vorname = $p;
                }
                // check if last name present (ends with al-X, ar-X, ad-X, etc.)
                else if(preg_match('/(*UTF8)(?<nachname>(?<pre>(a|ā).)\-\s?(?<family>[^\s]+))$/i', $p, $match)) {
                    // make dmg plain
                    $forename = mb_substr($p, 0, mb_strlen($p) - mb_strlen($match['nachname']));
                    $db_search_name = $forename .' al-'.$match['family'];
                    db_get_single_val('select dmg_plain(?)', array($db_search_name), $name_plain, Datenbank::$db);
                    $name_plain = preg_replace('/[^a-z]/', '', $name_plain);

                    //TODONEXT: ZIEL UMSTELLEN VON TABELLENAUSGABE IN PERSONEN ERZEUGEN FÜR JEDE ZEILE!

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
            }

/*                    $table .= <<<HTML
                <tr>
                    <td>$c</td>
                    <td>$orig</td>
                    <td><ul>$split</ul></td>
                </tr>
HTML;
*/
            /*$c = 1;
            foreach($arr_new_names as $new_name) {
                $group = '';
                if(is_array($new_name[3]))
                    $group = $new_name[3]['group_name'] . ' (' . $new_name[3]['id'] . ')';
                echo sprintf(
                    "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                    $c++, $new_name[0], $new_name[1], $new_name[2], $group
                );
            }
            */
        }
    }

    // ========================================================================================================
    class Personengruppe {
    // ========================================================================================================
        public static $db_groups = array();

        public  $db_id = false,
                $group_name = null,
                $family_name = null;

        // ----------------------------------------------------------------------------------------------------
        public static function db_personengruppen_auslesen() {
        // ----------------------------------------------------------------------------------------------------
            $sql = "
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
            ";

            Personengruppe::$db_groups = array();
            foreach(Datenbank::$db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $g = new Personengruppe;
                $g->db_id = $row['id'];
                $g->group_name = $row['group_name'];
                $g->family_name = $row['family_name'];
                Personengruppe::$db_groups[$g->family_name] = $row;
            }
        }
    }

    // ========================================================================================================
    class Dokument {
    // ========================================================================================================
        public static $db_docs = array();

        public  $db_id = null,
                $aufnahmen = array();
    }

    // ========================================================================================================
    class Datenbank {
    // ========================================================================================================
        public static $db = null;

        // ----------------------------------------------------------------------------------------------------
        public static function start() {
        // ----------------------------------------------------------------------------------------------------
            Datenbank::$db = db_connect();
        }

        // ----------------------------------------------------------------------------------------------------
        public static function dokumente_einlesen() {
        // ----------------------------------------------------------------------------------------------------
            foreach(Datenbank::$db->query('select id, signature from documents', PDO::FETCH_ASSOC) as $row)
                Dokument::$db_docs[$row['signature']] = $row['id'];
        }
    }

    // ========================================================================================================
    function import_test_run() {
    // ========================================================================================================
        echo <<<HTML
            <h2>Import-Testlauf</h2>
HTML;
        Datenbank::start();
        Datenbank::dokumente_einlesen();

        $c_doc = $c_lines = $c_relevant = $c_exist = 0;

        // Alle Tabellenzeilen einlesen
        foreach(Datenbank::$db->query("select *, replace(dmg_plain(datum), E'\011', ' ') plain_datum from neu limit 500", PDO::FETCH_ASSOC) as $doc)
            Tabellenzeile::einlesen($doc);

        foreach(Tabellenzeile::$alle as $z)
            Aufnahme::aus_zeile($z);

        echo sprintf(
            "%s Zeilen, %s Aufnahmen, %s vorhanden in der DB, %s Personen in DB",
            count(Tabellenzeile::$alle),
            count(Aufnahme::$alle),
            Aufnahme::$anz_bereits_existierende,
            count(Person::$db_persons)
        );

        // Nun Aufnahmen extrahieren
    }
?>
