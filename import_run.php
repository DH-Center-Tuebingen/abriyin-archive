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
                $dok_typ = null,
                $art = null; // verweis auf vor/rückseite

        // ----------------------------------------------------------------------------------------------------
        public static function aus_zeile($z) {
        // ----------------------------------------------------------------------------------------------------
            $a = new Aufnahme;
            $a->tabellenzeile = $z;
            if(!$a->ermittle_signatur())
                return false;

            if(isset(Dokument::$alle[$a->signatur])) {
                Aufnahme::$anz_bereits_existierende++;
                return false; // existiert bereits in der DB
            }

            $a->art = '';
            if(preg_match('/^(?<art>A|B|MS)/', $z->dif, $match))
                $a->art = $match['art'];

            $a->dok_typ = ($a->art == 'MS' ? 'other' : 'letter');

            $a->ist_rueckseite = preg_match('/\br\s?(?<frontside>[0123ABD]\d+-\d\:\s?\d+)\b/', $z->dif, $match);
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
            foreach(array('adressat', 'absender', 'weitere') as $person_feld) {
                $this->{$person_feld} = Person::erzeuge($this->tabellenzeile->{$person_feld}, $this);
            }
        }
    }

    // ========================================================================================================
    class Person {
    // ========================================================================================================
        public static $alle = array();
        public static $arr_orig_names = array();

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
                $vollname = null,
                $originaltext = null,
                $notizen = array();

        // ----------------------------------------------------------------------------------------------------
        public function make_vollname() {
        // ----------------------------------------------------------------------------------------------------
            $this->vollname = '';
            if($this->familienname != '')
                $this->vollname = $this->familienname;
            $this->vollname .= ', ' . $this->vorname;
            $this->vollname .= ', ' . $this->beiname;
            $this->vollname = trim(preg_replace('/\s+/', ' ', $this->vollname), ' ,');
        }

        // ----------------------------------------------------------------------------------------------------
        public static function personen_einlesen() {
        // ----------------------------------------------------------------------------------------------------
            Personengruppe::personengruppen_auslesen();
            Person::$alle = array();
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
                $p->db_id = $row['id'];
                $p->sex = $row['sex'];
                $p->vorname = $row['forename_translit'];
                $p->beiname = $row['byname_translit'];
                $p->familienname = $row['lastname_translit'];
                $p->familienname_plain = $p->familienname_kanon_plain = $row['ln'];
                if(ends_with('al-', $p->familienname_plain))
                    $p->familienname_kanon_plain = 'al-' . mb_substr($p->familienname_plain, 0, -3);
                $p->vollname_kanon_plain = preg_replace('/[^a-z]/', '', $row['fn'] . $row['bn'] . $p->familienname_kanon_plain);
                if(isset(Personengruppe::$db_groups[$p->familienname]))
                    $p->personengruppe = Personengruppe::$db_groups[$p->familienname];
                $p->make_vollname();
                Person::$alle[$p->vollname_kanon_plain] = $p;
            }
        }

        // ----------------------------------------------------------------------------------------------------
        public static function erzeuge($text, $aufnahme) {
        // ----------------------------------------------------------------------------------------------------
            $persons_found = array();
            $orig = unify_diacritics(trim($text));
            if($orig == '')
                return $persons_found;

            $pdb = $pn = '';
            $pers = $orig;

            // remove parentheses/brackets and contained text
            $pers = preg_replace('/\[.*?\]/', '', $pers);
            $pers = preg_replace('/\(.*?\)/', '', $pers);

            // remove "seine frau"
            $pers = str_ireplace('seine frau', '', $pers);

            // general replacements of Names
            $pers = preg_replace(
                array('/(*UTF8)\bqais\b/i', '/(*UTF8)\bsaif\b/i', '/(*UTF8)\bfaiṣal\b/i', '/(*UTF8)\bsulaimān\b/i', '/(*UTF8)\bsamīḥ\b/i', '/(*UTF8)\bsamiḥ\b/i', '/(*UTF8)\šīḫa\b/i'),
                array('Qays', 'Sayf', 'Fayṣal', 'Sulaymān', 'Samḥ', 'Samḥ', 'Šayḫa'),
                $pers);

            $pers = trim(preg_replace('/\s+/', ' ', $pers));

            if(in_array(mb_strtolower(preg_replace('/\s/', '', $pers)), array('o.a.', 'oa')))
                return $persons_found;

            // try to split up into multiple person infos
            $pax = preg_split('/[\/,;+]|und/', $pers);

            // for each person
            foreach($pax as $p) {
                $p = $orig_text = trim($p);

                // verschiedene Schreibweisen von Muhsin
                if(in_array($p, array(
                    'Muḥsin',
                    'Muḥsin b. Zahrān',
                    'Muḥsin b. Zahrān al-ʿAbrī',
                    'Muḥsin b. Zahrān b. Muḥammad',
                    'Muḥsin b. Zahrān b. Muḥammad al-ʿAbrī'
                ))) {
                    $p = 'Muḥsin b. Zahrān b. Muḥammad b. Ibrāhīm al-ʿAbrī';
                }

                $pers_obj = null;
                if($p != '' && isset(Person::$arr_orig_names[$p])) {
                    $pers_obj = Person::$arr_orig_names[$p];
                }
                else {
                    $pers_obj = new Person;
                    $pers_obj->originaltext = $orig_text;

                    // check if group of persons mentioned
                    if(preg_match('/(*UTF8)\balle\b/i', $p, $match)) {
                        $name_plain = preg_replace('/[^a-z]/', '', $p);
                        if(isset(Person::$alle[$name_plain])) {
                            $pers_obj = Person::$alle[$name_plain];
                        }
                        else {
                            $pers_obj->notizen[] = sprintf('"%s" wurde als Person angelegt, scheint aber eine Gruppe von Personen zu sein [P_NAME_GROUP]. Voller Eintrag: "%s"', $p, $pers);
                            $pers_obj->vorname = $p;
                            Person::$alle[$name_plain] = $pers_obj;
                        }
                    }
                    // check if last name present (ends with al-X, ar-X, ad-X, etc.)
                    else if(preg_match('/(*UTF8)(?<nachname>(?<pre>(a|ā).)\-\s?(?<family>[^\s]+))$/i', $p, $match)) {
                        // make dmg plain
                        $forename = trim(mb_substr($p, 0, mb_strlen($p) - mb_strlen($match['nachname'])));
                        $db_search_name = $forename .' al-'.$match['family'];
                        db_get_single_val('select dmg_plain(?)', array($db_search_name), $name_plain, Datenbank::$db);
                        $name_plain = preg_replace('/[^a-z]/', '', $name_plain);
                        $pers_obj->vollname_kanon_plain = $name_plain;
                        if(isset(Person::$alle[$name_plain])) {
                            $pers_obj = Person::$alle[$name_plain];
                        }
                        else {
                            $pers_obj->vorname = $forename;
                            $pers_obj->familienname = $match['family'] . ', al-';
                            if(isset(Personengruppe::$db_groups[$pers_obj->familienname]))
                                $pers_obj->personengruppe = Personengruppe::$db_groups[$pers_obj->familienname];
                            Person::$alle[$name_plain] = $pers_obj;
                        }
                    }
                    // any other name -> assume first name
                    else if(preg_replace('/[^a-z]/', '', $p) != '') {
                        $name_plain = preg_replace('/[^a-z]/', '', $p);
                        if(isset(Person::$alle[$name_plain])) {
                            $pers_obj = Person::$alle[$name_plain];
                        }
                        else {
                            $pers_obj->notizen[] = sprintf('"%s" konnte nicht als voller Name identifiziert werden. Der Text wurde als Vorname interpretiert [P_NAME_PARTIAL]. Voller Eintrag: "%s"', $p, $pers);
                            $pers_obj->vorname = $p;
                            Person::$alle[$name_plain] = $pers_obj;
                        }
                    }
                    // nothing to recognize
                    else {
                        $pers_obj = null;
                    }

                    if($pers_obj !== null) {
                        $pers_obj->make_vollname();
                        Person::$arr_orig_names[$p] = $pers_obj;
                    }
                }

                if($pers_obj !== null)
                    $persons_found[] = $pers_obj;
            }

            return $persons_found;
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
        public static function personengruppen_auslesen() {
        // ----------------------------------------------------------------------------------------------------
            $sql = "
                select id, group_name,
                -- left(family_name, strpos(family_name, ',') - 1) family_name
                family_name
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
                Personengruppe::$db_groups[$g->family_name] = $g;
            }
        }
    }

    // ========================================================================================================
    class Dokument {
    // ========================================================================================================
        public static $alle = array();

        public  $db_id = false,
                $aufnahme = null,
                $notizen = array();
                //$weitere_aufnahmen = array();

        // ----------------------------------------------------------------------------------------------------
        public static function aus_aufnahme($a) {
        // ----------------------------------------------------------------------------------------------------
            // already in DB?
            if(isset(Dokument::$alle[$a->signatur]))
                return;

            if($a->ist_rueckseite) {
                return; // TODO: was wenn info auf der Rückseite ist?
            }

            $d = new Dokument;
            $d->aufnahme = $a;
            $z = $a->tabellenzeile;
            $d->notizen[] = sprintf(
                "ORIGINALZEILE:\nNR: %s\nDIF: %s\nJAHR: %s\DATUM: %s\ADRESSAT: %s\nABSENDER: %s\nWEITERE: %s",
                $z->nr, $z->dif, $z->jahr, $z->datum, $z->adressat, $z->absender, $z->weitere
            );
            Dokument::$alle[$a->signatur] = $d;
        }
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
            foreach(Datenbank::$db->query('select id, signature from documents', PDO::FETCH_ASSOC) as $row) {
                $d = new Dokument;
                $d->db_id = $row['id'];
                Dokument::$alle[$row['signature']] = $d;
            }
        }
    }

    // ========================================================================================================
    function import_test_run() {
    // ========================================================================================================
        echo '<h2>Import-Testlauf</h2>', PHP_EOL;

        Datenbank::start();
        Datenbank::dokumente_einlesen();
        Person::personen_einlesen();

        $sql = "select *, replace(dmg_plain(datum), E'\011', ' ') plain_datum from neu limit 20000";
        foreach(Datenbank::$db->query($sql, PDO::FETCH_ASSOC) as $doc)
            Tabellenzeile::einlesen($doc);

        foreach(Tabellenzeile::$alle as $z)
            Aufnahme::aus_zeile($z);

        foreach(Aufnahme::$alle as $z_nr => $a)
            Dokument::aus_aufnahme($a);

        result_preview();
    }

    // ========================================================================================================
    function result_preview() {
    // ========================================================================================================
        echo <<<X
            <style>
                ul {
                    padding-left: 1.5em;
                    margin-bottom: 0;
                }
                .vorname {
                    background-color: #ffccff;
                }
                .nachname {
                    background-color: #66ff66;
                }
                .gruppe {
                    background-color: lightgray;
                    color: dimgray;
                    white-space: nowrap;
                }
                .dbid {
                    background-color: LemonChiffon;
                }
                .nw {
                    white-space: nowrap;
                }
                table {
                    display: none;
                }
                .loading {
                    font-weight: bold;
                    font-size: larger;
                }
            </style>
X;
        $c_zeilen = count(Tabellenzeile::$alle);
        $c_aufn = count(Aufnahme::$alle);
        $c_doks = count(Dokument::$alle);
        $c_pers = count(Person::$alle);

        uasort(Person::$alle, function($a, $b) {
            //return strcmp($a->vollname, $b->vollname);
            $cmp = strcmp($a->familienname, $b->familienname);
            if($cmp == 0)
                $cmp = strcmp($a->vorname, $b->vorname);
            if($cmp == 0)
                $cmp = strcmp($a->beiname, $b->beiname);
            return $cmp;
        });
        uasort(Dokument::$alle, function($a, $b) {
            if(!$a->aufnahme || !$b->aufnahme)
                return -1;
            return strcmp($a->aufnahme->signatur, $b->aufnahme->signatur);
        });

        $aufnahmen = '';
        /*$c = 0;
        foreach(Aufnahme::$alle as $foo => $a) {
            $aufnahmen .= sprintf("<h4># %s</h4>\n<pre>%s</pre><hr />\n", ++$c, var_export($a, true));
        }*/

        $c_doks_neu = 0;
        $dokumente = <<<TABLE
            <p>
                Legende:
                <span class="nachname">Familienname</span>
                <span class="vorname">Vorname</span>
                <span class="gruppe">Personengruppe</span>
                <span class="dbid">ID in der Datenbank</span>
            </p>
            <p class='loading'>Tabelle lädt ...</p>
            <table class="table table-striped table-bordered table-responsive table-condensed">
            <tr>
                <th>Sig.</th>
                <th>Bündel</th>
                <th>Jahr</th>
                <th>Monat</th>
                <th>Tag</th>
                <th>Adressaten</th>
                <th>Absender</th>
                <th>Weitere</th>
                <th>Orig. Nr</th>
                <th>Orig. Dif</th>
                <th>Orig. Jahr</th>
                <th>Orig. Datum</th>
                <th>Orig. Adressaten</th>
                <th>Orig. Absender</th>
                <th>Orig. Weitere</th>
            </tr>
TABLE;
        foreach(Dokument::$alle as $foo => $d) {
            if($d->db_id !== false)
                continue;
            ++$c_doks_neu;
            foreach(array('adressat', 'absender', 'weitere') as $pers_typ) {
                ${$pers_typ} = '';
                if(count($d->aufnahme->{$pers_typ}) == 0)
                    continue;
                ${$pers_typ} = '<ul>';
                foreach($d->aufnahme->{$pers_typ} as $pers) {
                    $n = '';
                    if($pers->familienname)
                        $n .= "<span class='nachname'>{$pers->familienname}</span>";
                    if($pers->familienname && $pers->vorname)
                        $n .= ', ';
                    if($pers->vorname)
                        $n .= "<span class='vorname'>{$pers->vorname}</span>";
                    if($pers->personengruppe)
                        $n .= " <span class='nw gruppe'><span class='glyphicon glyphicon-link'></span> {$pers->personengruppe->group_name}</span>";
                    if($pers->db_id)
                        $n .= " <span class='nw dbid'><span class='glyphicon glyphicon-record'></span> {$pers->db_id}</span>";
                    ${$pers_typ} .= "<li>$n</li>";
                }
                ${$pers_typ} .= '</ul>';
            }
            $dokumente .= sprintf(
                "<tr><td class='nw'>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $d->aufnahme->signatur, $d->aufnahme->buendel, $d->aufnahme->datum_jahr, $d->aufnahme->datum_monat, $d->aufnahme->datum_tag, $adressat, $absender, $weitere, $d->aufnahme->tabellenzeile->nr, $d->aufnahme->tabellenzeile->dif, $d->aufnahme->tabellenzeile->jahr, $d->aufnahme->tabellenzeile->datum, $d->aufnahme->tabellenzeile->adressat, $d->aufnahme->tabellenzeile->absender, $d->aufnahme->tabellenzeile->weitere
            );
        }
        $dokumente .= '</table>';

        $c_pers_neu = 0;
        $c_group_found = 0;
        $personen = <<<TABLE
            <p class='loading'>Tabelle lädt ...</p>
            <table class="table table-striped table-bordered table-responsive table-condensed">
            <tr>
                <th>#</th>
                <th>Familienname</th>
                <th>Vorname</th>
                <th>Beiname</th>
                <th>Personengruppe</th>
                <th>Originaltext</th>
            </tr>
TABLE;
        foreach(Person::$alle as $foo => $p) {
            if($p->db_id !== false)
                continue;
            if($p->personengruppe)
                $c_group_found++;
            $personen .= sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>\n",
                ++$c_pers_neu, $p->familienname, $p->vorname, $p->beiname, $p->personengruppe ? $p->personengruppe->group_name : '', $p->originaltext
            );
        }
        $personen .= '</table>';

        // tabbed output of all this
        echo <<<HTML
            <p><ul>
                <li>$c_zeilen Tabellenzeilen</li>
                <li>$c_aufn Aufnahmen aus Tabellenzeilen extrahiert</li>
                <li>$c_doks_neu neue Dokumente ($c_doks insgesamt)</li>
                <li>$c_pers_neu neue Personen ($c_pers insgesamt); bei $c_group_found von den neuen Personen konnte eine bestehende Personengruppe ermittelt werden</li>
            </ul></p>
            <ul class="nav nav-tabs">
                <li class="active"><a data-toggle="tab" href="#dokumente">Neue Dokumente</a></li>
                <li><a data-toggle="tab" href="#personen">Neue Personen</a></li>
                <!--<li><a data-toggle="tab" href="#aufnahmen">Aufnahmen</a></li>-->
            </ul>
            <div class="tab-content">
                <div id="dokumente" class="tab-pane active">
                    <h3>Neue Dokumente</h3>
                    $dokumente
                </div>
                <div id="personen" class="tab-pane">
                    <h3>Neue Personen</h3>
                    $personen
                </div>
                <!--<div id="aufnahmen" class="tab-pane ">
                    <h3>Aufnahmen</h3>
                    $aufnahmen
                </div>-->
            </div>
            <script>
                $(document).ready(function() {
                    $('.loading').toggle();
                    $('table').toggle();
                });
            </script>
HTML;
    }
?>
