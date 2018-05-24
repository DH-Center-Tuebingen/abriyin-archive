<?
    // ========================================================================================================
    class HatNotizen {
    // ========================================================================================================
        public $notizen = array();

        // ----------------------------------------------------------------------------------------------------
        public function importnotizen_erzeugen() {
        // ----------------------------------------------------------------------------------------------------
            return join("\n\n", $this->notizen);
        }
    }

    // ========================================================================================================
    class Tabellenzeile extends HatNotizen {
    // ========================================================================================================
        public static $alle = array();

        public  $zeile = null,
                $nr = null,
                $dif = null,
                $jahr = null,
                $datum = null,
                $adressat = null,
                $absender = null,
                $weitere = null,
                $inhalt = null,
                $relevant = false,
                $fehlerstatus = null;

        // ----------------------------------------------------------------------------------------------------
        public static function einlesen($row) {
        // ----------------------------------------------------------------------------------------------------
                $z = new Tabellenzeile;
                foreach(array('zeile', 'nr', 'dif', 'jahr', 'adressat', 'absender', 'weitere', 'inhalt') as $prop) {
                    $z->{$prop} = trim($row[$prop]);
                }
                $z->nr = preg_replace('/\s/', '', $z->nr);
                $z->dif = preg_replace('/\s+/', ' ', $z->dif);
                $z->datum = $row['plain_datum'];
                Tabellenzeile::$alle[$z->nr] = $z;
        }

        // ----------------------------------------------------------------------------------------------------
        public function importnotiz_formatieren() {
        // ----------------------------------------------------------------------------------------------------
            $t = '';
            $n = array(
                'AUFNAHME NR' => $this->nr,
                'EXCEL-ZEILE' => $this->zeile,
                'DIF' => $this->dif,
                'JAHR' => $this->jahr,
                'DATUM' => $this->datum,
                'ADRESSAT' => $this->adressat,
                'ABSENDER' => $this->absender,
                'WEITERE' => $this->weitere
            );

            foreach($n as $label => $val) {
                $val = trim(strval($val));
                if($val == '')
                    continue;
                $t .= sprintf(
                    "%s%s: %s",
                    ($t == '' ? '' : "\n"),
                    $label,
                    $val
                );
            }

            return $t;
        }
    }

    // ========================================================================================================
    class Aufnahme extends HatNotizen { // verarbeitete Tabellenzeile
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
                $ist_rueckseite = null,
                $nr_kehrseite = null,
                $tabellenzeile = null,
                $dok_typ = null,
                $dokument = null, // zeiger auf zugeordnetes Dokument
                $art = null; // verweis auf vor/rückseite

        // ----------------------------------------------------------------------------------------------------
        public static function aus_zeile($z) {
        // ----------------------------------------------------------------------------------------------------
            $a = new Aufnahme;
            $a->tabellenzeile = $z;
            if(!$a->ermittle_signatur()) {
                $z->notizen[] = "Kann Signatur nicht ermitteln [Z_NO_SIG]";
                $z->fehlerstatus = 'Z_NO_SIG';
                $z->relevant = false;
                return false;
            }

            if(isset(Dokument::$alle[$a->signatur])) {
                Aufnahme::$anz_bereits_existierende++;
                $z->notizen[] = "Existiert bereits in der Datenbank [Z_IN_DB]";
                $z->fehlerstatus = 'Z_IN_DB';
                $z->relevant = false;
                return false; // existiert bereits in der DB
            }

            $a->art = '';
            if(preg_match('/^(?<art>A|B|MS)/', $z->dif, $match))
                $a->art = $match['art'];

            $a->dok_typ = ($a->art == 'MS' ? 'other' : 'letter');

            /* backside formats
                "A; rD7-10: 08; 2 Z"
                "MS; rD10-16: 25 A; 13 Z (Gedicht)"
                "B; rD10-16: 35A; 5 Z abgeschn."
                "B; r D63-115: 15"
                "A; r04-28; 2 Z"
                "A; r04-28; 2 Z"
                sonstige?
            */
            $a->ist_rueckseite = preg_match('/\bv\s?(?<frontside>[0123ABD]\d+-\d+[a-z]?(\:\s?\d+)?\s?-?[a-z]?)($|[; \/,])/i', $z->dif, $match);
            if($a->ist_rueckseite)
                $a->nr_kehrseite = preg_replace('/\s/', '', $match['frontside']);
            $ist_vorderseite = preg_match('/\br\s?(?<backside>[0123ABD]\d+-\d+[a-z]?(\:\s?\d+)?\s?-?[a-z]?)($|[; \/,])/i', $z->dif, $match);
            if($ist_vorderseite)
                $a->nr_kehrseite = preg_replace('/\s/', '', $match['backside']);

            if(starts_with('Wiederholung', $z->dif) || starts_with('Wdh', $z->dif)) {
                $z->relevant = false;
                $z->notizen[] = "Wiederholung [Z_WDH]";
                $z->fehlerstatus = 'Z_WDH';
                return false;
            }

            $z->relevant = true;
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

        // ----------------------------------------------------------------------------------------------------
        public function zu_dokument_zuordnen() {
        // ----------------------------------------------------------------------------------------------------
            if(!$this->ist_rueckseite)
                return;

            if(isset(Aufnahme::$alle[$this->nr_kehrseite])) {
                $aufnahme_vorderseite = Aufnahme::$alle[$this->nr_kehrseite];
                if($aufnahme_vorderseite->tabellenzeile->relevant
                    && $aufnahme_vorderseite->dokument !== null
                    && $aufnahme_vorderseite->dokument->db_id === false)
                {
                    $aufnahme_vorderseite->dokument->weitere_aufnahmen[] = $this;
                    $this->dokument = $aufnahme_vorderseite->dokument;
                    $this->dokument->rueckseiteninfo_einpflegen($this); // merge names into dokument
                    return;
                }
            }

            $this->tabellenzeile->relevant = false;
            if(isset(Tabellenzeile::$alle[$this->nr_kehrseite]) && !Tabellenzeile::$alle[$this->nr_kehrseite]->relevant) {
                $this->tabellenzeile->notizen[] = sprintf("Rückseite; Vorderseite %s (Excel-Zeile %s) bereits irrelevant [Z_FRONT_IRRELEVANT]", Tabellenzeile::$alle[$this->nr_kehrseite]->nr, Tabellenzeile::$alle[$this->nr_kehrseite]->zeile);
                $this->tabellenzeile->fehlerstatus = 'Z_FRONT_IRRELEVANT';
            }
            else {
                $this->tabellenzeile->notizen[] = "Rückseite; keine Vorderseite gefunden [Z_NO_FRONT]";
                $this->tabellenzeile->fehlerstatus = 'Z_NO_FRONT';
            }
        }

        // ----------------------------------------------------------------------------------------------------
        public static function rueckseiten_zu_dokument_zuordnen() {
        // ----------------------------------------------------------------------------------------------------
            foreach(Aufnahme::$alle as $nr => $a)
                if($a->ist_rueckseite)
                    $a->zu_dokument_zuordnen();
        }

        // ----------------------------------------------------------------------------------------------------
        public function haengende_rueckseiten_finden() {
        // ----------------------------------------------------------------------------------------------------
            // manche Rückseiten haben keinen Verweis auf die Vorderseite
            if($this->nr_kehrseite === null)
                return;
            $kehrseite = isset(Aufnahme::$alle[$this->nr_kehrseite]) ? Aufnahme::$alle[$this->nr_kehrseite] : null;
            if($kehrseite) {
                $kehrseite->nr_kehrseite = $this->tabellenzeile->nr;
                $kehrseite->ist_rueckseite = !$this->ist_rueckseite;
            }
        }

        // ----------------------------------------------------------------------------------------------------
        public static function z_no_front_aufloesen() {
        // ----------------------------------------------------------------------------------------------------
            foreach(Aufnahme::$alle as $a) {
                if($a->tabellenzeile->fehlerstatus != 'Z_NO_FRONT')
                    continue;

                // manche Aufnahmen verweisen auf Aufnahmen, die zusammengefasst sind, z.B.
                // TODO

                // manche Aufnahmen verweisen auf sich selbst, falls multiple Aufnahmen zusammengefasst sind, z.B.
                // D16-30: 14/15 | B; vD16-30: 15; 32 Z + R
                // Solch eine Aufnahme wird ein eigenes Dokument
                $nr = $a->tabellenzeile->nr;
                $dif = $a->tabellenzeile->dif;
                if(preg_match('/(?<teil1>[0123ABD]\d+-\d+[a-z]?)\:\s*(?<teil2>(\d+\s?-?[a-z]?\/?)+)($|[;, ])/i', $nr, $match_nr)
                    && preg_match('/\b(v|r)\s?(?<teil1>[0123ABD]\d+-\d+[a-z]?)\:\s*(?<teil2>(\d+\s?-?[a-z]?\/?)+)($|[;, ])/i', $dif, $match_dif))
                {
                    if(trim($match_nr['teil1']) != trim($match_dif['teil1']))
                        continue;
                    // nun gucken ob sich nach dem Doppelpunkt zahlen überlappen
                    // Modifikatoren wegmachen, z.B das "-a" bei: D18-32:26/27-a | B; vD18-32: 27, 3 Z + R
                    $arr_aufn_nr = explode('/', preg_replace('/[^\/\d]/', '', $match_nr['teil2']));
                    if(!is_array($arr_aufn_nr))
                        continue;
                    $arr_aufn_dif = explode('/', preg_replace('/[^\/\d]/', '', $match_dif['teil2']));
                    if(!is_array($arr_aufn_dif))
                        continue;
                    $overlap = array_intersect($arr_aufn_dif, $arr_aufn_nr);
                    if(is_array($overlap) && count($overlap) > 0) {
                        // Fehler resetten
                        $a->tabellenzeile->fehlerstatus = null;
                        $a->tabellenzeile->notizen = array();
                        $a->ist_rueckseite = false;
                        // Aufnahme aus dieser Gülle machen.
                        Dokument::aus_aufnahme($a);
                    }
                }
            }
        }
    }

    // ========================================================================================================
    class Person extends HatNotizen {
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
                $edit_status = null;

        // ----------------------------------------------------------------------------------------------------
        public static function neue_in_db_schreiben() {
        // ----------------------------------------------------------------------------------------------------
            global $TABLES;
            $persons_pk_seq = $TABLES['persons']['primary_key']['sequence_name'];

            foreach(Person::$alle as $person) {
                if($person->db_id !== false)
                    continue;
                $sql = "insert into persons (sex, forename_translit, lastname_translit, edit_note, edit_status)
                    values (?, ?, ?, ?, 'imported')";
                $values = array($person->sex, $person->vorname, $this->familienname, $person->importnotizen_erzeugen());
                $stmt = Datenbank::$db->prepare($sql);
                if($stmt === false)
                    return proc_error(l10n('error.db-prepare'), Datenbank::$db);
                if(false === $stmt->execute($values))
                    return proc_error(l10n('error.db-execute'), $stmt);
                $person->db_id = Datenbank::$db->lastInsertId($persons_pk_seq);
                // assoc with person group, if any
                if($person->personengruppe) {
                    db_prep_exec(
                        'insert into person_of_group (person, person_group) values (?, ?)',
                        array($person->db_id, $person->personengruppe->db_id),
                        $stmt,
                        Datenbank::$db
                    );
                }
            }
        }

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
        public function ist_gleich($p) {
        // ----------------------------------------------------------------------------------------------------
            if($this->db_id !== false && $this->db_id == $p->db_id)
                return true;

            if($this->vollname == $p->vollname)
                return true;

            return false;
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


            // handle cases like "1) Nāṣir b. Masʿūd b. Nāṣir al-ʿAbrī 2) Sulaimān b. Zahrān 3) Muḥsin b. Zahrān"
            $pers = preg_replace('/^1\)/', '', $pers); // remove "1)" at beginning
            $pers = preg_replace('/\s\d\)\s/', ' / ', $pers); // replace other numbers with separators

            // truncate consecutive white spaces to single white space
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
                    'Muḥsin b. Zahrān b. Muḥammad al-ʿAbrī',
                    'Muḥsin b. Zahrān b. Muḥammad al-ʿAbr' // typo obviously
                ))) {
                    $p = 'Muḥsin b. Zahrān b. Muḥammad b. Ibrāhīm al-ʿAbrī';
                }
                else if(starts_with('Šāmis b. ʿAbdallāh', $p)) {
                    $p = 'Šāmis b. ʿAbdallāh b. Ḫalfān aḏ-Ḏuhlī';
                }

                $pers_obj = null;
                if($p != '' && isset(Person::$arr_orig_names[$p])) {
                    $pers_obj = Person::$arr_orig_names[$p];
                }
                else {
                    $pers_obj = new Person;
                    $pers_obj->edit_status = 'imported';
                    $pers_obj->originaltext = $orig_text;
                    $pers_obj->notizen[] = sprintf('Aus Aufnahme "%s" (Excel-Zeile %s) [P_AUFN]', $aufnahme->tabellenzeile->nr, $aufnahme->tabellenzeile->zeile);
                    $pers_obj->notizen[] = sprintf('Originaltext: "%s" [P_ORIG_TEXT]', $orig_text);

                    $pers_obj->sex = (mb_strpos($orig_text, 'bint') === false ? 'm' : 'f');

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
                            $lookup_family_name = $pers_obj->familienname = $match['family'] . ', al-';

                            if(in_array($match['family'], array('ʿAbrīya', 'ʿAbrīyāt'))) {
                                $pers_obj->sex = 'f'; // some Abriya have no "bint" in the name
                                $lookup_family_name = 'ʿAbrī, al-';
                            }

                            if(in_array($match['family'], array('ʿAbrīyin', 'ʿAbriyīn', 'ʿAbrīyīn', 'ʿAbrīyūn', 'ʿAbrīyān')))
                                $lookup_family_name = $pers_obj->familienname = 'ʿAbrī, al-';

                            if(isset(Personengruppe::$db_groups[$lookup_family_name]))
                                $pers_obj->personengruppe = Personengruppe::$db_groups[$lookup_family_name];
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
    class Dokument extends HatNotizen {
    // ========================================================================================================
        public static $alle = array();

        public  $db_id = false,
                $signatur = null,
                $buendel = null,
                $datum_jahr = null,
                $datum_monat = null,
                $datum_tag = null,
                $adressat = array(),
                $absender = array(),
                $weitere = array(),
                $dok_typ = null,
                $aufnahme = null,
                $inhalt = null,
                $weitere_aufnahmen = array(),
                $edit_status = null;

        // ----------------------------------------------------------------------------------------------------
        public static function neue_in_db_schreiben() {
        // ----------------------------------------------------------------------------------------------------
            Person::neue_in_db_schreiben();

            global $TABLES;
            $documents_pk_seq = $TABLES['documents']['primary_key']['sequence_name'];
            $person_map = array(
                'adressat' => 'document_recipients',
                'absender' => 'document_primary_agents',
                'weitere' =>  'document_persons'
            );
            foreach(Dokument::$alle as $doc) {
                if($doc->db_id !== false)
                    continue;
                // insert document
                $sql = "insert into documents (signature, pack_nr, date_year, date_month, date_day, \"type\", summary, edit_note, edit_status)
                    values (?, ?, ?, ?, ?, ?, ?, ?, 'imported')";
                $values = array($doc->signatur, $doc->buendel, $doc->datum_jahr, $doc->datum_monat, $doc->datum_tag,
                    $doc->dok_typ, $doc->inhalt, $doc->importnotizen_erzeugen());
                $stmt = Datenbank::$db->prepare($sql);
                if($stmt === false)
                    return proc_error(l10n('error.db-prepare'), Datenbank::$db);
        		if(false === $stmt->execute($values))
        			return proc_error(l10n('error.db-execute'), $stmt);
                $doc->db_id = Datenbank::$db->lastInsertId($documents_pk_seq);
                // assign people
                foreach($person_map as $pers_typ => $db_table) {
                    foreach($doc->{$pers_typ} as $person) {
                        db_prep_exec(
                            "insert into $db_table (document, person) values (?, ?)",
                            array($doc->db_id, $person->db_id),
                            $stmt,
                            Datenbank::$db
                        );
                    }
                }
            }
        }

        // ----------------------------------------------------------------------------------------------------
        public function importnotizen_erzeugen() {
        // ----------------------------------------------------------------------------------------------------
            $n = parent::importnotizen_erzeugen();

            if(count($this->aufnahme->notizen) > 0)
                $n .= "\n\n" . $this->aufnahme->importnotizen_erzeugen();

            foreach($this->weitere_aufnahmen as $a)
                if(count($a->notizen) > 0)
                    $n .= "\n\n" . $a->importnotizen_erzeugen();

            return $n;
        }

        // ----------------------------------------------------------------------------------------------------
        public static function aus_aufnahme($a) {
        // ----------------------------------------------------------------------------------------------------
            // already in document list
            if(isset(Dokument::$alle[$a->signatur]))
                return;

            if($a->ist_rueckseite) {
                return; // folgender Durchlauf mit Zuordnung
            }

            $d = new Dokument;
            $a->tabellenzeile->relevant = true; // sicherstellen, kann sein dass das verändert wurde
            $d->edit_status = 'imported';
            $d->aufnahme = $a;
            $d->aufnahme->dokument = $d;
            $d->inhalt = $d->aufnahme->tabellenzeile->inhalt;

            // copy all info from frontside aufnahme
            foreach(array('signatur', 'buendel', 'datum_jahr', 'datum_monat', 'datum_tag', 'adressat', 'absender', 'weitere', 'dok_typ') as $prop)
                $d->{$prop} = $d->aufnahme->{$prop};

            $d->notizen[] = $a->tabellenzeile->importnotiz_formatieren();
            Dokument::$alle[$a->signatur] = $d;
        }

        // ----------------------------------------------------------------------------------------------------
        public function rueckseiteninfo_einpflegen($aufnahme) {
        // ----------------------------------------------------------------------------------------------------
            // Inhalt anhängen
            if($aufnahme->tabellenzeile->inhalt) {
                if($this->inhalt === null)
                    $this->inhalt = '';
                $this->inhalt .= (($this->inhalt != '' ? "\n\n" : '') . $aufnahme->tabellenzeile->inhalt);
            }

            // Personeninformationen anhängen
            foreach(array('adressat', 'absender', 'weitere') as $pers_typ) {
                foreach($aufnahme->{$pers_typ} as $person) {
                    // check if person already exists, otherwise add from rückseite
                    $existiert = false;
                    foreach($this->{$pers_typ} as $schon_da) {
                        if($schon_da->ist_gleich($person)) {
                            $existiert = true;
                            break;
                        }
                    }
                    if(!$existiert)
                        $this->{$pers_typ}[] = $person;
                }
            }

            // Notiz für Quellzeile aus der Tabelle
            $this->notizen[] = $aufnahme->tabellenzeile->importnotiz_formatieren();
        }

        // ----------------------------------------------------------------------------------------------------
        public static function signaturen_bestimmen() {
        // ----------------------------------------------------------------------------------------------------
            // immer versuchen, die Signatur der Aufnahme "B" zu entlocken, falls möglich.
            $doks_neu = array();

            foreach(Dokument::$alle as $sig => $d) {
                if($d->db_id === false) { // nur neue Dokumente
                    if($d->aufnahme->art != 'B') {
                        // Suche "B" Aufnahme:
                        for($i = 0; $i < count($d->weitere_aufnahmen); $i++) {
                            $a = $d->weitere_aufnahmen[$i];
                            if($a->art == 'B') { // diese vertauschen mit der bisher signaturgebenden aufnahme
                                $temp = $a;
                                $d->weitere_aufnahmen[$i] = $d->aufnahme;
                                $d->aufnahme = $temp;
                                $d->aufnahme->tabellenzeile->relevant = true; // sicherstellen dass relevant
                                break;
                            }
                        }
                    }
                    $sig = $d->signatur = $d->aufnahme->signatur;
                }
                $doks_neu[$sig] = $d;
            }
            Dokument::$alle = $doks_neu;
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
            $a->haengende_rueckseiten_finden();

        foreach(Aufnahme::$alle as $z_nr => $a)
            Dokument::aus_aufnahme($a);

        Aufnahme::rueckseiten_zu_dokument_zuordnen();
        Aufnahme::z_no_front_aufloesen();
        Dokument::signaturen_bestimmen();

        ergebnis_vorschau();
    }

    // ========================================================================================================
    function ergebnis_vorschau() {
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
                td.code {
                    min-width: 200px;
                    font-family: monospace;
                }
                td.sm {
                    max-width: 220px;
                }
                table.font-sm {
                    font-size: smaller;
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
            return strcmp($a->signatur, $b->signatur);
        });
        uasort(Tabellenzeile::$alle, function($a, $b) {
            return strcmp($a->nr, $b->nr);
        });

        $aufnahmen = <<<TABLE
            <p class='loading'>Tabelle lädt ...</p>
            <table class="table table-striped table-bordered table-responsive table-condensed">
            <tr>
                <th>#</th>
                <th>Excel-Zeile</th>
                <th>Grund</th>
                <th>Nr</th>
                <th>Dif</th>
                <th>Jahr</th>
                <th>Datum</th>
                <th>Adressaten</th>
                <th>Absender</th>
                <th>Weitere</th>
            </tr>
TABLE;
        $c = 0;
        foreach(Tabellenzeile::$alle as $z) {
            if($z->relevant)
                continue;
            $aufnahmen .= sprintf(
                "<tr><td>%s</td><td>%s</td><td><i>%s</i></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
                ++$c, $z->zeile, join('; ', $z->notizen), $z->nr, $z->dif, $z->jahr, $z->datum, $z->adressat, $z->absender, $z->weitere
            );
        }
        $aufnahmen .= "</table>\n";

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
            <table class="table table-striped table-bordered table-responsive table-condensed font-sm">
            <tr>
                <th>Signatur</th>
                <th>Bündel</th>
                <th>Typ</th>
                <th>Jahr</th>
                <th>Monat</th>
                <th>Tag</th>
                <th>Adressaten</th>
                <th>Absender</th>
                <th>Weitere</th>
                <th>Inhalt</th>
                <th>Importnotiz</th>
            </tr>
TABLE;
        foreach(Dokument::$alle as $d) {
            if($d->db_id !== false)
                continue;
            ++$c_doks_neu;
            foreach(array('adressat', 'absender', 'weitere') as $pers_typ) {
                ${$pers_typ} = '';
                if(count($d->{$pers_typ}) == 0)
                    continue;
                ${$pers_typ} = '<ul>';
                foreach($d->{$pers_typ} as $pers) {
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
                "<tr><td class='nw'>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class='sm'>%s</td><td class='code'>%s</td></tr>\n",
                $d->signatur, $d->buendel, $d->dok_typ, $d->datum_jahr, $d->datum_monat, $d->datum_tag, $adressat, $absender, $weitere, html($d->inhalt, 0, false, true), html($d->importnotizen_erzeugen(), 0, false, true)
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
                <th>m/f</th>
                <th>Personengruppe</th>
                <th>Originaltext</th>
                <th>Importnotiz</th>
            </tr>
TABLE;
        foreach(Person::$alle as $foo => $p) {
            if($p->db_id !== false)
                continue;
            if($p->personengruppe)
                $c_group_found++;
            $personen .= sprintf(
                "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><pre>%s</pre></td></tr>\n",
                ++$c_pers_neu, $p->familienname, $p->vorname, $p->sex, $p->personengruppe ? $p->personengruppe->group_name : '', $p->originaltext, $p->importnotizen_erzeugen()
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
                <li><a data-toggle="tab" href="#aufnahmen">Ignorierte Aufnahmen</a></li>
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
                <div id="aufnahmen" class="tab-pane">
                    <h3>Ignorierte Aufnahmen</h3>
                    $aufnahmen
                </div>
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
