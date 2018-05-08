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
                $empfaenger = array(),
                $absender = array(),
                $andere = array(),
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
            $a->signatur = '';
            $match = array();
            $has_sig_aufnahme = false;
            // match "B02-3: 25" or similar
            if(preg_match('/^(?<sig>[BD]\d+)-(?<bundle>[^:]+):\s*(?<aufnahme>.+)$/', $z->nr, $match)) {
                $a->buendel = $match['bundle'];
                $has_sig_aufnahme = true;
            }
            else if(preg_match('/^(?<sig>[0123A]\d+)-(?<aufnahme>.+)$/', $z->nr, $match)) {
                $a->buendel = '';
                $has_sig_aufnahme = true;
            }

            if($has_sig_aufnahme) {
                $sig = ltrim($match['sig'], '0');
                $aufnahme = ltrim($match['aufnahme'], '0');
                if($pos_slash = strpos($aufnahme, '/')) // e.g. "35/36" -> take only first aufnahme = "35"
                    $aufnahme = substr($aufnahme, 0, $pos_slash);
                $a->signatur = sprintf('%s-%s', $sig, $aufnahme);
            }

            if($a->signatur == '')
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
        protected function datum_ermitteln() {
        // ----------------------------------------------------------------------------------------------------
            
        }

        // ----------------------------------------------------------------------------------------------------
        protected function personen_ermitteln() {
        // ----------------------------------------------------------------------------------------------------

        }
    }

    // ========================================================================================================
    class Person {
    // ========================================================================================================
        public  $db_id = false,
                $vorname = null,
                $familinenname = null,
                $beiname = null,
                $personengruppe = null,
                $notizen = array();
    }

    // ========================================================================================================
    class Personengruppe {
    // ========================================================================================================
        public  $db_id = false,
                $name = null;
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
            "%s Zeilen, %s Aufnahmen, %s vorhanden in der DB",
            count(Tabellenzeile::$alle), count(Aufnahme::$alle), Aufnahme::$anz_bereits_existierende
        );

        // Nun Aufnahmen extrahieren
    }
?>
