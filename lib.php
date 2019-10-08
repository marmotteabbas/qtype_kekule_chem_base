<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Serve question type files
 *
 * @since      2.0
 * @package    qtype_kekule_chem

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$kekulePluginsPath = get_config('mod_kekule', 'kekule_dir');

if (empty($kekulePluginsPath))
    $kekulePluginsPath = '/local/kekulejs/';  // default location;
require_once($CFG->dirroot . $kekulePluginsPath . 'lib.php');

// consts
class qtype_kekule_chem_compare_methods {
    const DEF_METHOD = 0;  // default
    const SMILES = 1;  // Exact match with SMILES
    const MOLDATA = 2;  // Exact match with molecule data, usually can be replaced with SMILES
    const PARENTOF = 11; // answer is parent structure of key molecule
    const CHILDOF = 12;  // answer is sub structure of key molecule
    //const MANUAL = 10;  // manually compare, not grade automatically
}
class qtype_kekule_chem_compare_levels {
    const DEF_LEVEL = 0;  // default
    const CONSTITUTION = 1;  // match with Constitution, ingore steroe
    const CONFIGURATION = 2;  // match with stereo
    const NON_BONDING_PAIRS = 3;
}

class qtype_kekule_chem_input_type {
    const MOLECULE = 0;
    const DOCUMENT = 1;
}

class qtype_kekule_chem_html
{
    const INPUT_TYPE_MOL = 'mol';
    const INPUT_TYPE_DOC = 'doc';
    // class for answer blank in question design
    const CLASS_DESIGN_VIEWER_BLANK = 'K-Chem-Question-Design-BlankViewer';
    const CLASS_DESIGN_ANSWER_BLANK = 'K-Chem-Question-Design-AnswerBlank';
    // class for answer blank in question solve by student
    const CLASS_BLANK = 'K-Chem-Question-Blank';
    const CLASS_DOC_BLANK = 'K-Chem-Question-Blank-Doc';
    const CLASS_MOL_BLANK = 'K-Chem-Question-Blank-Mol';
    const CLASS_BLANK_ANSWER = 'K-Chem-Question-Answer';
    const CLASS_CORRECT_RESPONSE = 'K-Chem-Question-CorrectResponse';
    // question body
    //const CLASS_QUESTION_BODY = 'K-Chem-Question-Body';
}

class qtype_kekule_chem_configs
{
    const DEF_MOL_COMPARER_URL = 'http://127.0.0.1:3000/mols/compare';
    const DEF_JS_SERVER_URL = 'http://127.0.0.1:3000/mols';

    const PATH_COMPARE = '/compare';
    const PATH_CONTAIN = '/contain';

    const DEF_KEKULE_DIR = '/local/kekulejs/';

    static public function getKekuleDir()
    {
        return kekulejs_configs::getScriptDir();
        /*
        if (empty($result))
            $result = self::DEF_KEKULE_DIR;
        return $result;
        */
    }
}

class qtype_kekule_chem_utils
{
    static public function postData($url, $data, $optional_headers = null)
    {
        $params = array('http' => array(
            'method' => 'POST',
            'content' => http_build_query($data, '', '&')
              // NOTE: here we appoint separator '&', otherwise '&amp' will be used and caused error in server side
        ));
        //var_dump(http_build_query($data));
        //die();
        if ($optional_headers !== null) {
            $params['http']['header'] = $optional_headers;
        }

        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) {
            throw new Exception("Problem with $url, $php_errormsg");
        }
        $response = @stream_get_contents($fp);
        if ($response === false) {
            throw new Exception("Problem reading data from $url, $php_errormsg");
        }
        return $response;
    }
    
    static public function is_electron_set_the_same($srcDetail, $targetDetail) {
        $nodes_src = json_decode($srcDetail->molData)->ctab->nodes;
        $nodes_target = json_decode($targetDetail->molData)->ctab->nodes;
        
        //Check all Atom from the target ...
        for ($key = 0; $key < sizeof($nodes_target); $key++) {
            //.. To compare to the atom src 
            for ($key2 = 0; $key2 < sizeof($nodes_src); $key2++) {
                //If we are on the same atom and Lones on it..
                if ($nodes_target[$key]->isotopeId == $nodes_src[$key2]->isotopeId
                    &&
                   (isset($nodes_target[$key]->attachedMarkers) && isset($nodes_src[$key2]->attachedMarkers))) {
                    
                    //$no_lone_everywhere = !$nodes_target[$key]->attachedMarkers && !$nodes_src[$key2]->attachedMarkers;
                    $same_size = sizeof($nodes_target[$key]->attachedMarkers) == sizeof($nodes_src[$key2]->attachedMarkers);
                    
                    $count_target = 0;
                    $count_src = 0;
                    //.. And we have the same number of pair, we count the electron
                    if (/*$no_lone_everywhere || */$same_size && sizeof($nodes_target[$key]->attachedMarkers) != 0) {
                        for ($i = 0; $i < sizeof($nodes_target[$key]->attachedMarkers); $i++){
                            $count_target = $count_target + $nodes_target[$key]->attachedMarkers[$i]->electronCount;
                            $count_src = $count_src + $nodes_src[$key2]->attachedMarkers[$i]->electronCount;
                        }
                        
                        // if we have the same count 
                        if ($count_target == $count_src) {
                            unset ($nodes_target[$key]);
                            unset ($nodes_src[$key2]);
                            $nodes_target = array_values($nodes_target);
                            $nodes_src = array_values($nodes_src);
                            $key = 0;
                            $key2 = 0;
                            break;
                        } 
                    } 
                    
                } elseif (!isset($nodes_target[$key]->attachedMarkers) && !isset($nodes_src[$key2]->attachedMarkers)
                           &&
                          $nodes_target[$key]->isotopeId == $nodes_src[$key2]->isotopeId) {
                            unset ($nodes_target[$key]);
                            unset ($nodes_src[$key2]);
                            $key = -1;
                            $key2 = 0;
                            $nodes_target = array_values($nodes_target);
                            $nodes_src = array_values($nodes_src);
                            break; 
                    }
            }
        }
        
        return empty ($nodes_src) && empty ($nodes_target);
    }
    
    static public function is_electron_set_the_same_v2($srcDetail, $targetDetail) {
        $nodes_src = json_decode($srcDetail->molData)->ctab->nodes;
        $nodes_target = json_decode($targetDetail->molData)->ctab->nodes;
        
        if (sizeof($nodes_src) == sizeof($nodes_target)) {
            for ($key = 0; $key < sizeof($nodes_target); $key++) {
                
                if (($nodes_target[$key]->isotopeId == $nodes_src[$key]->isotopeId) && (isset($nodes_target[$key]->attachedMarkers) && isset($nodes_src[$key]->attachedMarkers))) {
                    $same_size = sizeof($nodes_target[$key]->attachedMarkers) == sizeof($nodes_src[$key]->attachedMarkers);
                    
                    if ($same_size) {
                        unset($nodes_target[$key]);
                        unset($nodes_src[$key]);
                        $nodes_target = array_values($nodes_target);
                        $nodes_src = array_values($nodes_src);
                        $key = -1;
                    }
                } elseif (($nodes_target[$key]->isotopeId == $nodes_src[$key]->isotopeId) && (!isset($nodes_target[$key]->attachedMarkers) && !isset($nodes_src[$key]->attachedMarkers))) {
                        unset($nodes_target[$key]);
                        unset($nodes_src[$key]);
                        $nodes_target = array_values($nodes_target);
                        $nodes_src = array_values($nodes_src);
                        $key = -1;
                }
            }

        }
        return empty ($nodes_src) && empty ($nodes_target);
    }
    
    static public function is_electron_set_the_same_v3($srcDetail, $targetDetail) {
            $connectors_src = json_decode($srcDetail->molData)->ctab->connectors;
            $connectors_target = json_decode($targetDetail->molData)->ctab->connectors;
            
            $nodes_src = json_decode($srcDetail->molData)->ctab->nodes;
            $nodes_target = json_decode($targetDetail->molData)->ctab->nodes;
            
            for ($key3=0; $key3 < sizeof($connectors_src); $key3++) {
                for ($key4 = 0; $key4 < sizeof($connectors_target); $key4++) {
                   $eq_iso_0_0 = $nodes_src[$connectors_src[$key3]->connectedObjs[0]]->isotopeId == $nodes_target[$connectors_target[$key4]->connectedObjs[0]]->isotopeId;
                   $eq_iso_1_1 = $nodes_src[$connectors_src[$key3]->connectedObjs[1]]->isotopeId == $nodes_target[$connectors_target[$key4]->connectedObjs[1]]->isotopeId;
                   $eq_iso_1_0 = $nodes_src[$connectors_src[$key3]->connectedObjs[1]]->isotopeId == $nodes_target[$connectors_target[$key4]->connectedObjs[0]]->isotopeId;
                   $eq_iso_0_1 = $nodes_src[$connectors_src[$key3]->connectedObjs[0]]->isotopeId == $nodes_target[$connectors_target[$key4]->connectedObjs[1]]->isotopeId;
                   
                   $indice_1 = -1;
                   $indice_2 = -1;
                   $indice_3 = -1;
                   $indice_4 = -1;
                   
                   if ($eq_iso_0_0 && $eq_iso_1_1) {
                       $indice_1 = 0;
                       $indice_2 = 0;
                       $indice_3 = 1;
                       $indice_4 = 1;
                   } elseif ($eq_iso_1_0 && $eq_iso_0_1) {
                       $indice_1 = 1;
                       $indice_2 = 0;
                       $indice_3 = 0;
                       $indice_4 = 1;
                   }
                   
                   if ($indice_1 != -1 && $indice_2 != -1 && $indice_3 != -1 && $indice_4 != -1) {
                       //Do the Job for the first PAIR
                       $first_count_ok = false;
                       
                       if (isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers) && isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers)) {
                            //Count if there is the same number of electron of the first pair
                            $count_target =0;
                            $count_src = 0;
                          //  if (sizeof($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers) && sizeof($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers)) {
                                for ($i = 0; $i < sizeof($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers); $i++){
                                    if (isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers[$i]->electronCount)) { 
                                        $count_target = $count_target + $nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers[$i]->electronCount;
                                    } 
                                }
                                
                                for ($i = 0; $i < sizeof($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers); $i++){
                                    if (isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers[$i]->electronCount)) { 
                                        $count_src = $count_src + $nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers[$i]->electronCount;
                                    }
                                }
                           // }
                            
                            if ($count_src == $count_target) {
                                $first_count_ok = true;
                            }
                       } elseif (!isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers) && !isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers)) {
                           $first_count_ok = true;
                       } else {
                           $empty_array = false;
                            if (isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers)) {
                               if (sizeof($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_1]]->attachedMarkers) == 0) {
                                   $empty_array = true;
                               }
                            }
                            
                            if (isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers)) {
                                if (sizeof($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_2]]->attachedMarkers) == 0) {
                                   $empty_array = true;
                               }
                            }
                            
                            if ($empty_array) {
                                $first_count_ok = true;
                            }
                            
                       }
                       
                       //Do the Job for the second PAIR
                       $second_count_ok = false;
                       
                       if (isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers) && isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers)) {
                            //Count if there is the same number of electron of the second pair
                            $count_target =0;
                            $count_src = 0;
                        //    if (sizeof($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers) && sizeof($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers)) {
                                for ($i = 0; $i < sizeof($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers); $i++){
                                    if (isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers[$i]->electronCount)) {
                                        $count_target = $count_target + $nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers[$i]->electronCount;
                                    }
                                }
                                
                                for ($i = 0; $i < sizeof($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers); $i++){
                                    if (isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers[$i]->electronCount)) {
                                     $count_src = $count_src + $nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers[$i]->electronCount;
                                    }
                                }
                          //  }

                            if ($count_src == $count_target) {
                                $second_count_ok = true;
                            }
                       } elseif (!isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers) && !isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers)) {
                           $second_count_ok = true;
                       } else {
                           $empty_array = false;
                            if (isset($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers)) {
                               if (sizeof($nodes_src[$connectors_src[$key3]->connectedObjs[$indice_3]]->attachedMarkers) == 0) {
                                   $empty_array = true;
                               }
                            }
                            
                            if (isset($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers)) {
                                if (sizeof($nodes_target[$connectors_target[$key4]->connectedObjs[$indice_4]]->attachedMarkers) == 0) {
                                   $empty_array = true;
                               }
                            }
                            
                            if ($empty_array) {
                                $second_count_ok = true;
                            }
                            
                       }
                       
                       if ($first_count_ok && $second_count_ok) {
                           unset($connectors_src[$key3]);
                           unset($connectors_target[$key4]);
                           $connectors_src = array_values($connectors_src);
                           $connectors_target = array_values($connectors_target);
                           $key4 = -1;
                           $key3 = -1;
                           break;
                       }
                       
                   }
                }
            }
      
        return empty ($connectors_src) && empty ($connectors_target);
    }
    
    
}

/**
 * Checks file access for Kekule Chem questions.
 * @package  qtype_kekule_chem
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function qtype_kekule_chem_base_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_kekule_chem_base', $filearea, $args, $forcedownload, $options);
}
