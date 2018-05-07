<?
    class Tabellenzeile {
        public  $nr = null,
                $dif = null,
                $jahr = null,
                $datum = null,
                $empfaenger = null,
                $absender = null,
                $andere = null,
                $inhalt = null;

        public static function einlesen($row) {
                $z = new Tabellenzeile;
                $z->nr = $row['nr'];
                return $z;
        }
    }

    class Aufnahme { // verarbeitete Tabellenzeile
        public  $signatur = null,
                $buendel = null,
                $datum_jahr = null,
                $datum_monat = null,
                $datum_tag = null,
                $empfaenger = array(),
                $absender = array(),
                $andere = array(),
                $notizen = array(),
                $hat_rueckseite = false,
                $kehrseite = null; // verweis auf vor/rückseite
    }

    class Person {
        public  $db_id = false,
                $vorname = null,
                $familinenname = null,
                $beiname = null,
                $personengruppe = null,
                $notizen = array();
    }

    class Personengruppe {
        public  $db_id = false,
                $name = null;
    }

    class Dokumente {
        public  $db_id = null,
                $aufnahmen = array();
    }

    // ========================================================================================================
    function import_test_run() {
    // ========================================================================================================
        echo <<<HTML
            <h2>Import-Testlauf</h2>
HTML;
        $db = db_connect();
        $c_doc = $c_lines = $c_relevant = $c_exist = 0;

        // Alle Tabellenzeilen einlesen
        $tab_zeilen = array();
        foreach($db->query('select * from neu', PDO::FETCH_ASSOC) as $doc) {
            $z = Tabellenzeile::einlesen($doc);
            $tab_zeilen[$z->nr] = $z;
        }

        // Nun Aufnahmen extrahieren
    }
?>
