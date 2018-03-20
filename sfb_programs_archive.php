<?php 
/*
This is an application that integrates an Airtable database with WordPress via the Airtable API. This file is included by the theme or child theme's functions.php. This code is dependent upon a specific database running in Airtable, linking an archive of 20 years of concert programs with composers and text translations.

Copyright © 2018 by Cantus Firmus LLC
*/
class ProgramArchives {

    private $airtable_app;
    private $airtable_key;

    public function __construct($airtable_app, $airtable_key) {
        $this->airtable_app = $airtable_app;
        $this->airtable_key = $airtable_key;
        add_shortcode('programs_archive', array($this, 'sfb_archive'));
    }

    private function query_airtable($table,$query) {
        $url_string       = "https://api.airtable.com/v0/" . $this->airtable_app . "/";

        $encoded_query    = urlencode($query);
        $modified_query   = str_replace("%3D", "=", $encoded_query);
        $modified_query   = str_replace("%26", "&", $modified_query);
        $modified_query   = str_replace("%27", "'", $modified_query);
        $modified_query   = str_replace("%3F", "?", $modified_query);
        $final_url        = $url_string . $table . "/" . $modified_query;

        $json 	          = $this->get_url($final_url);
        $object           = json_decode($json);

        if ($object->records) {
        	return $object->records;
        } else {
        	return $object;
        }
    }		

    function sfb_archive($atts) {
        //the main function called by the shortcode. Actions are dependent upon URL parameters
        ob_start();

        if ($atts["show"]=="composers") {
            // the shortcode parameter "show" indicates that this is the Composer Index

            if ($_GET['composer']) {
            //Show data for that given Composer based on the Airtable ID in the URL

                $this->sfb_single_composer($_GET['composer']);

            } elseif ($_GET['work']) {
            //Show data for that given Work based on the Airtable ID in the URL
                if ($_GET['program']) { 
                    $program_id = $_GET['program'];
                } else {
                    $program_id = "";
                }

                $this->sfb_single_work($_GET['work'], $program_id);

            } else {
            //No URL parameter is present.  Show the main listing of composers broken down alphabetically
                
                $this->sfb_list_composers();

            }
        } else {
            //The shortcode parameter "show" is either missing or set to "Programs".  

            if ($_GET['program']) {
            //Show data for the given Program based on the Airtable ID in the URL
              
                $this->sfb_single_program($_GET['program']);
                  
            } else {
            //no Program ID is present in the URL.  Show listing of programs broken down by season.

                $this->sfb_list_programs_by_season();
            
            }
        }
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    public function sfb_single_composer($composer_id) {
        //Given a single composer ID in the URL, show works list for that composer

        $works_query        = "?filterByFormula=Composer_ID='" . $composer_id . "'&sort[0][field]=Title&sort[0][direction]=asc";
        $Works_by_Composer  = $this->query_airtable("Works",$works_query);
        $Composer           = $this->query_airtable("Composers", $composer_id);

        echo "<h1>" . $Composer->fields->FirstName . " " . $Composer->fields->LastName . " " . $Composer->fields->Dates . "</h1>";

        //In case there is ever a desire to include composer bio
        //echo wpautop($this_composer->Notes);

        echo "<h2>Vocal Works Performed by the San Francisco Bach Choir</h2>";

        echo "<ul>";
        foreach($Works_by_Composer as $this_work) {
            echo "<li><a href='/programs-archive/composer-index/?work=" . $this_work->id . "'>" . $this_work->fields->Title . "</a></li>";
        } 
        echo "</ul>";
    }

    public function sfb_single_work($work_id,$program_id) {

        $Work           = $this->query_airtable("Works",$work_id);
        $this_work      = $Work->fields;
        $composer_name  = $this_work->Composer_Name[0];
        $composer_id    = $this_work->Composer[0];
 
        if ($program_id != "") {
            echo "<div style='display: inline-block; margin-right: 20px;'><strong><a href='/programs-archive/?program=" . $program_id  . "&view=Works'>&larr; Back to Concert Program</a></strong> </div>";
        }

        echo "<h1>" . $this_work->Title . "</h1>"; echo "<a href
        ='/programs-archive/composer-index/?composer=" . $composer_id
        . "'>" . $composer_name . " " . $this_work->Composer_Dates[0]
        . "</a>";
        
        echo "<div style='margin-top: 15px'>";
            if ($this_work->Text == "") {
                echo "<p>No text is available for this work.</p>";
            } else {
                echo wpautop($this_work->Text);
                echo "<p>Note: These translations are Copyright © 1995–" . date(Y) . " San Francisco Bach Choir. Please do not distribute or use these translations without the express written permission of the San Francisco Bach Choir.</p>";
            }
        echo "</div>";


    }

    public function sfb_list_composers() {
        $query          = "?sort[0][field]=LastName&sort[0][direction]=asc";
        $Composers      = $this->query_airtable("Composers",$query);

        //The list of composers is broken down alphabetically according to the letter ranges defined by this array.
        $alphabet_groupings = array(
            array("A","B"),
            array("C","D"),
            array("E","G"),
            array("H","K"),
            array("L","N"),
            array("O","R"),
            array("S","T"),
            array("U","Z")
        ); 

        $composers_obj      = new ArrayObject($Composers);
        $composer_iter      = $composers_obj->getIterator();

        foreach($alphabet_groupings as $this_grouping) {
          echo "<h3>" . $this_grouping[0] . "&mdash;" . $this_grouping[1] . "</h3>";

          echo "<ul>";

          while ($composer_iter->valid()) {
            $this_composer = $composer_iter->current();
            $firstchar     = $this_composer->fields->Name[0];

        
            //If the name is after the second character in the group, then break out of composers to print the next section header.
            if ($firstchar > $this_grouping[1]) {
              break;
            }
            //Otherwise, if the name is more than or equal to the first character in group, then print the composer.
            elseif ($firstchar >= $this_grouping[0]) {
              echo "<li><a href='/programs-archive/composer-index/?composer=" . $this_composer->id . "'>" . $this_composer->fields->Name . " " . $this_composer->fields->Dates . "</a></li>";
            }

            else {
              //Do nothing        
            }

            $composer_iter->next();
          }
          echo "</ul>";
        } 
    }
  
    public function sfb_single_program($program_id) {

        $Program        = $this->query_airtable("Programs",$program_id);

        echo "<div style='display: inline-block; margin-right: 20px;'><strong><a href='/programs-archive/'>&larr; Programs Archive</a></strong> </div>";

        echo "<h1>" . $Program->fields->Name . " &mdash; " . $Program->fields->Date_Text . "</h1>";
        
        echo "<p>For this concert program: <a href='/programs-archive?program=". $program_id . "&view=Details'>Details</a> | <a href='/programs-archive?program=". $program_id . "&view=Works'>Translations</a> | <a href='/programs-archive?program=". $program_id . "&view=Notes'>Notes</a> </p>";

        echo "<div class='sfb-program-content'>";

        $default_display = "<h2>Program Details</h2>";
        $default_display .= wpautop($Program->fields->ProgramDetails);


        if ($_GET['view']) {
            switch ($_GET['view']) {
                case "Works":
                    echo "<h2>Programmed Works</h2>";
                    echo "<p>Click work titles for texts and translations. Click composer name for related works.</p>";    
                    $this->sfb_program_works($Program->fields->Works,$program_id);
                    break;

                case "Notes":
                    echo "<h2>Program Notes</h2>";
                    $this->sfb_program_notes($Program->fields->ProgramNotes);
                    break;

                default:
                    echo $default_display;
                    break;
            }

        } else {
             echo $default_display;
        }
        echo "</div>";
    }

    public function sfb_program_notes($notes) {

            if ($notes) {
                echo wpautop($notes);
                echo "<p>Please do not distribute or use these notes without the express written permission of the San Francisco Bach Choir.</p>";
            } else {
                echo "<p>No program notes available for this program.</p>";
            }
    }

    public function sfb_program_works($program_works,$program_id) {
        
        $works_url_array= array();       
        foreach ($program_works as $this_work) {
            array_push($works_url_array, "RECORD_ID()='" . $this_work . "'");
        }

        $works_url_list = implode(",", $works_url_array);
        $query          = "?filterByFormula=OR(" . $works_url_list . ")";
        $Works          = $this->query_airtable("Works",$query);

        if (empty($Works)) {
            echo "<p>No work titles have yet been assigned to this concert program.</p>";
        } else {

            echo "<ul>";
            
            foreach ($Works as $this_work) {

                echo "<li style='padding-bottom: 15px;'>";
                    if ($this_work->fields->Text) {
                        echo "<strong><a href='/programs-archive/composer-index?work=" . $this_work->id . "&program=" . $program_id . "'>" . $this_work->fields->Title . "</a></strong><br/>";
                    } else {
                        echo "<strong style='color: #999;'>" . $this_work->fields->Title . "</strong><br/>";
                    }
                   // echo "<strong><a href='/programs-archive/composer-index?work=" . $this_work->id . "'>" . $this_work->fields->Title . "</a></strong><br/>";
                    echo "<a href=''>" . $this_work->fields->Composer_Name[0] . "</a> " . $this_work->fields->Composer_Dates[0];
                echo "</li>";
            }

            echo "</ul>";
        }            
    }

    public function sfb_list_programs_by_season() {
    /*
    * Airtable only allows up to 100 records to be returned by an API query, so this needed to be broken down into separate pages.
    * By default, Seasons from 2002 to the present are shown.  URL parameters determine a range of seasons to show on a second page.
    */

        if (isset($_GET['start'])) {
            $start  = "'" . $_GET['start'] . "'";
            if (isset($_GET['end'])) {
                $end    = "'" . $_GET['end'] . "'" ;
            } else {
                $end    = "'8/1/2002'";
            }                   
        } else {
            $start  = "'8/1/2002'";
            $end    = "TODAY()";
        }

        $start_year     = 1991;
        $current_year   = date(Y);

        $parsed_date    = "DATETIME_PARSE('8/1/2002', 'D MMM YYYY HH:mm')";

        $query          = "?&sort[0][field]=Season&sort[0][direction]=asc&sort[1][field]=Date&sort[1][direction]=asc";
        $query          .= "&filterByFormula=AND(IS_AFTER({Date}, " . $start . ")";
        $query          .= ", IS_BEFORE({Date}, " . $end . "))";

        $Programs       = $this->query_airtable("Programs",$query);


        echo "<h2>Programs by Concert Season</h2>";

        $prev_season = '';

        foreach($Programs as $index=>$this_program) {
            $fields         = $this_program->fields;
            $this_season    = $fields->Season;
            $program        = $fields->ProgramDetails;
            $program_works  = $fields->Works;
            $program_notes  = $fields->ProgramNotes;

            $program_date   = strtotime($this_program->fields->Date);
            $date           = date('m', $program_date) . "/" . date('Y', $program_date);

            if ($this_season != $prev_season) {      
                if ($prev_season > '') {
                    echo "<div style='clear: both'></div>";
                    echo "</div> <!-- end table div -->"; 
                }

                echo "<h3>" . $this_season . "</h3>";
                echo "<div class='sfb-programs-table'>";
                $prev_season = $this_season;
            }

            echo "<div class='sfb-program-archive-row'>";

                echo "<div style='float: left;'>";
                    echo $date;
                echo "</div>";

                echo "<div style='width: 50%; float: left;'>";
                    echo  $this_program->fields->Name;
                echo "</div>";

                echo "<div style='float: left;'>";
                    if ($program == "") {
                        echo "&nbsp;";
                    } else {
                        echo "<a href='/programs-archive?program=". $this_program->id . "&view=Details'>Details</a>";
                    }
                    
                echo "</div>";

                echo "<div style='float: left;'>";
                    if ($program_works) {
                        echo "<a href='/programs-archive?program=". $this_program->id . "&view=Works'>Translations</a> ";
                    } else {
                        echo "&nbsp;";
                    }
                echo "</div>";


                echo "<div style='float: left;'>";
                    if ($program_notes != "") {
                        echo "<a href='/programs-archive?program=". $this_program->id . "&view=Notes'>Notes</a>";
                    }
                echo "</div>";
            echo "<div style='clear: both'></div>";
            echo "</div> <!-- end row -->";
        }

        if ($prev_season > '') {
            
            echo "</div> <!-- end table div -->";  
        }
    }

    private function get_url($url) 
    {
        $ch = curl_init();
         
        if($ch === false)
        {
            die('Failed to create curl object');
        }
         
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
       
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->airtable_key,
            'Accept: application/json',
      ));
        $data = curl_exec($ch);
        curl_close($ch);
       
        return $data;
    }
}
