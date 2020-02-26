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
    /**
     * Compare electron charge of a molecule
     * @param object $srcDetail 
     * @param object $targetDetail
     * @return boolean
     */
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
    /**
     * return a merged array of nodes and connectors of a molecule
     * @param object $molecule
     * @return array
     */
    static public function merge_nodes_and_connetors($molecule) {
        $array_merge = array();

        //It seeams array_merge() disturb orders for unknow reasons 
        foreach($molecule->ctab->nodes as $mcn) {
            $array_merge[] = $mcn;
        }
        
        foreach($molecule->ctab->connectors as $mcc) {
            $array_merge[] = $mcc;
        }

        return $array_merge;
    }
    /**
     * return all items of a mollecule
     * @param object $src
     * @return array
     */
    static public function list_elements($src) {
        return json_decode($src->molData)->root->children->items;
    }
    /**
     * By elements give merged nodes and connectors list
     * @param array $elems
     * @return string
     */
    static public function all_molecules_merged_bond_and_nodes_list($elems) {
        $mols = array();
        foreach ($elems as $es) {
            if ($es->__type__ == "Kekule.Molecule" ) {
                $mols[] = qtype_kekule_chem_utils::merge_nodes_and_connetors($es);
            } else {
                $mols[] = "autre chose";
            }
        }
        
        return $mols;
    }
    /**
     * Check if element of the arrows are already in the list
     * @param type $arrow_list
     * @param type $informations_coord_tab
     * @return boolean
     */
    static public function is_already_in_list($arrow_list, $informations_coord_tab) {
        $already = false;
        foreach ($arrow_list as $al) {
            if ($al['electronCount'] == $informations_coord_tab['electronCount'] && 
                            $al['origin']['molecule'] == $informations_coord_tab['origin']['molecule'] &&
                                $al['origin']['point'] == $informations_coord_tab['origin']['point'] &&
                                    $al['target']['point'] == $informations_coord_tab['target']['point'] &&
                                        $al['target']['molecule'] == $informations_coord_tab['target']['molecule']) {
                $already = true;
            }
        }
        
        return $already;
    }
    /**
     * Return an array of all arrows in the drawing
     * @param object $srcDetail
     * @return array
     */
    static public function get_arrows_list($srcDetail/*, $targetDetail*/) {
    //    $root_items_target = json_decode($targetDetail->molData)->root;
        if (isset(json_decode($srcDetail->molData)->root)) {
            $root_items_src = json_decode($srcDetail->molData)->root;

    /*     if ((!isset($root_items_src) && isset($root_items_target)) || (isset($root_items_src) && !isset($root_items_target)) || (!isset($root_items_src) && !isset($root_items_target))) {
           //I don't know yet 
         } else {*/
             $arrow_list = array();
             foreach ($root_items_src->children->items as $it) {
                 if ($it->__type__ === "Kekule.Glyph.ElectronPushingArrow") {
                 //  "Kekule.Glyph.ElectronPushingArrow"
                   $informations_coord_tab  = array();
                   //Take coord od ... orgin point in first ... Target point in second
                   foreach ($it->ctab->nodes as $k => $no) {
                       $temp_data = explode(",", str_replace("]", "", str_replace("@[", "", $no->coordStickTarget)));
                       $temp_data["molecule"] = $temp_data[0];
                       $temp_data["point"] = $temp_data[1];
                       unset($temp_data[0]);
                       unset($temp_data[1]);
                       if ($k == 0) {
                           $informations_coord_tab["origin"] = $temp_data;
                       } elseif ($k == 1) {
                           $informations_coord_tab["target"] = $temp_data;
                       }
                   }
                   $informations_coord_tab["electronCount"] = $it->electronCount;

                   if (!qtype_kekule_chem_utils::is_already_in_list($arrow_list, $informations_coord_tab)) {
                     $arrow_list[] = $informations_coord_tab;
                   }
                 }
             }
        } else {
             return array();
        }
     //   } 
        
        return $arrow_list;
    }
    /**
     * Return all bonds connected to a point
     * @param int $point
     * @param array $elemList
     * @return type
     */
    static public function find_covalent_connected_to_a_point($point, $elemList) {
        
        $covalentList = array();
        //Looking for the covalent connecting to the two point
        foreach ($elemList as $idse => $se) {
            if (isset($se->connectedObjs)) {
                foreach ($se->connectedObjs as $sec) {
                    if ($sec == $point) {
                        $covalentList[$idse] = $se;
                    }
                }
            }
         }
         
         return $covalentList;  
    }
    /**
     * Count the number of bonds in a list
     * @param array $list
     * @return int
     */
    static public function count_covalent_in_the_list($list) {
        $count = 0;
        foreach ($list as $l) {
            if ($l->__type__ == "Kekule.Bond") {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Travel a path from a point and compare if it's the same in the student answer than in the teacher answer
     * @param int $original_point
     * @param int $target_point
     * @param object $srcElemList
     * @param object $targetElemList
     * @param array $covalentListOriginal
     * @param array $covalentListTarget
     * @return boolean
     */
    static public function compare_path($original_point, $target_point, $srcElemList, $targetElemList, $covalentListOriginal, $covalentListTarget) {       
        $covalentListTargetTemp = $covalentListTarget;
        
        $listOfTBondTargetRemoved = array();
        $listOfBondOriginalRemoved = array();
        
        $listOfJoinedObjectOrigine = array();
        $listOfJoinedObjectTarget = array();
        //if There is the same number of covalent from the atom
        if (sizeof($covalentListTarget) == sizeof($covalentListOriginal)) {
            foreach ($covalentListOriginal as $id => $clo) {
                //$bondok = false;
                foreach ($covalentListTargetTemp as $i => $clt) {
                     $joined_obj_origine = -1;
                    if ($clo->bondOrder == $clt->bondOrder) {
                        if (($srcElemList[$clo->connectedObjs[0]]->isotopeId == $targetElemList[$clt->connectedObjs[0]]->isotopeId) && $clo->connectedObjs[0] != $original_point && $clt->connectedObjs[0] != $target_point) {
                            $joined_obj_origine = $clo->connectedObjs[0];
                            $joined_obj_target = $clt->connectedObjs[0];
                        }
                        
                        if (($srcElemList[$clo->connectedObjs[1]]->isotopeId == $targetElemList[$clt->connectedObjs[1]]->isotopeId) && $clo->connectedObjs[1] != $original_point && $clt->connectedObjs[1] != $target_point) {
                            $joined_obj_origine = $clo->connectedObjs[1];
                            $joined_obj_target = $clt->connectedObjs[1];
                        }
                        
                        if (($srcElemList[$clo->connectedObjs[0]]->isotopeId == $targetElemList[$clt->connectedObjs[1]]->isotopeId) && $clo->connectedObjs[0] != $original_point && $clt->connectedObjs[1] != $target_point) {
                            $joined_obj_origine = $clo->connectedObjs[0];
                            $joined_obj_target = $clt->connectedObjs[1];
                        }
                        
                        if (($srcElemList[$clo->connectedObjs[1]]->isotopeId == $targetElemList[$clt->connectedObjs[0]]->isotopeId) && $clo->connectedObjs[1] != $original_point && $clt->connectedObjs[0] != $target_point) {
                            $joined_obj_origine = $clo->connectedObjs[1];
                            $joined_obj_target = $clt->connectedObjs[0];
                        }
                        
                        if ($joined_obj_origine != -1) {
                            $listOfTBondTargetRemoved[] = $i;
                            $listOfBondOriginalRemoved[] = $id;
                            
                            unset($covalentListTargetTemp[$i]);
                            $listOfJoinedObjectOrigine[$joined_obj_origine] = $joined_obj_origine;
                            $listOfJoinedObjectTarget[$joined_obj_target] = $joined_obj_target;
                            break;
                        } /*else {
                            return false;
                        }*/
                        
                    }
                }
                
                //foreach ($clo->connectedObjs as $cloc)
            }
        } else {
            return false;
        }
        
        if (sizeof($listOfJoinedObjectOrigine) != 0/* && sizeof($covalentListTargetTemp) != 0*/) {
           //We remove the origine point from the answer list at firt if it's matching for every bonds
            unset($srcElemList[$original_point]);
            unset($targetElemList[$target_point]);
            
            //We remove the two original point of the list of elem src
            foreach ($listOfBondOriginalRemoved as $lobor) {
                unset($srcElemList[$lobor]);
            }
            
            //We remove the two original point of the list of elem teacher answer
            foreach ($listOfTBondTargetRemoved as $lobtr) {
                unset ($targetElemList[$lobtr]);
            }
            
          ///  if (qtype_kekule_chem_utils::count_covalent_in_the_list($srcElemList) == qtype_kekule_chem_utils::count_covalent_in_the_list($targetElemList)) {
                $listOfJoinedObjectOrigineCount = 0;
                foreach($listOfJoinedObjectOrigine as $ljoo) {
                    if (sizeof(qtype_kekule_chem_utils::find_covalent_connected_to_a_point($ljoo, $srcElemList)) > 0) {
                        $listOfJoinedObjectOrigineCount++;
                    }
                }
                
                $listOfJoinedObjectTargetCount = 0;
                foreach($listOfJoinedObjectTarget as $ljot) {
                    if (sizeof(qtype_kekule_chem_utils::find_covalent_connected_to_a_point($ljot, $targetElemList)) > 0) {
                        $listOfJoinedObjectTargetCount++;
                    }
                }
            
                $countMatchingResult = 0;
                
                if ($listOfJoinedObjectTargetCount == $listOfJoinedObjectOrigineCount) {
                
                    //check for the rest of the points
                    foreach ($listOfJoinedObjectOrigine as $sel) {
                        $covalentListOriginal = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($sel, $srcElemList);
                            foreach ($listOfJoinedObjectTarget as $tel) {
                                $covalentListTarget = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($tel, $targetElemList);
                                if ((sizeof($covalentListOriginal) == sizeof($covalentListTarget)) && sizeof($covalentListOriginal) > 0 && $srcElemList[$sel]->isotopeId == $targetElemList[$tel]->isotopeId) {
                                    if (qtype_kekule_chem_utils::compare_path($sel,$tel,$srcElemList,$targetElemList,$covalentListOriginal,$covalentListTarget) == true) {
                                        $countMatchingResult++;
                                        break;
                                    }
                               } 
                            }
                    }
                    
                    if ($countMatchingResult == $listOfJoinedObjectTargetCount) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
               
          /*  } else {
                return false;
            }*/
        } else {
            return (sizeof($covalentListOriginal) == sizeof($covalentListTarget)) && sizeof($covalentListOriginal) == 0;
        }
    }
    /**
     * Compare the arrows of the teacher answer and the student answer 
     * @param object $srcDetail
     * @param object $targetDetail
     * @return boolean
     */
    static public function compare_arrows($srcDetail, $targetDetail) {
        $countCorrespondingArrows = 0;
        
        $arrow_list_src = qtype_kekule_chem_utils::get_arrows_list($srcDetail);
        
        $arrow_list_target = qtype_kekule_chem_utils::get_arrows_list($targetDetail);
        
        // If there is not the same number of arrows in the correct anwser & in the student answer
        if (sizeof($arrow_list_src) != sizeof($arrow_list_target)) {
            return false;
        }
        
        //Check origin an target are the same for each arrrows
        foreach ($arrow_list_src as $i => $als) {
            foreach ($arrow_list_target as $id => $alt) {
                //Get arrow, bond, nodes from src
                $elems_src = qtype_kekule_chem_utils::list_elements($srcDetail);
                $all_mols_src = qtype_kekule_chem_utils::all_molecules_merged_bond_and_nodes_list($elems_src);
                
                $all_mols_src_2 = $all_mols_src;
                
                //Get arrow, bond, nodes from target
                $elems_target = qtype_kekule_chem_utils::list_elements($targetDetail);
                $all_mols_target = qtype_kekule_chem_utils::all_molecules_merged_bond_and_nodes_list($elems_target);
                
                $all_mols_target_2 = $all_mols_target;
                //** check if same type **/
                //origin type
                $is_origin_type_the_same = $all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->__type__ == $all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->__type__;      
                //target type
                $is_target_type_the_same = $all_mols_src[$als["target"]["molecule"]][$als["target"]["point"]]->__type__ == $all_mols_target[$alt["target"]["molecule"]][$alt["target"]["point"]]->__type__;
                
                if ($is_origin_type_the_same && $is_target_type_the_same && $alt["electronCount"] == $als["electronCount"] && $als["origin"]["molecule"] == $alt["origin"]["molecule"] && $als["target"]["molecule"] == $alt["target"]["molecule"]) {
                    $answer1 = false;
                            
                    if ($all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->__type__ == "Kekule.Atom") {
                        //Check origin isotope
                        $is_origin_iso_the_same = $all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->isotopeId == $all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->isotopeId; 
                        
                        if ($is_origin_iso_the_same) {
                            $covalentListOriginal = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($als["origin"]["point"],$all_mols_src[$als["origin"]["molecule"]]);
                            $covalentListTarget = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($alt["origin"]["point"],$all_mols_target[$alt["origin"]["molecule"]]);
                            $answer1 = qtype_kekule_chem_utils::compare_path($als["origin"]["point"],$alt["origin"]["point"],$all_mols_src[$als["origin"]["molecule"]],$all_mols_target[$alt["origin"]["molecule"]], $covalentListOriginal, $covalentListTarget);
                        /*
                            if ($answer1) {
                                $countCorrespondingArrows++;
                                unset ($arrow_list_target[$id]);
                            }*/
                        } 
                    } else {
                        if ($all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->bondType == "covalent") {
                            //check bondorder
                            $bond_same = $all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->bondOrder == $all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->bondOrder;
                            
                            if ($bond_same == true) {
                                /*Check Connected Objects nature*/
                                //Src bond first object isotope student answer
                                $sfa = $all_mols_src[$als["origin"]["molecule"]][$all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->connectedObjs[0]];                            
                                //Src bond second object isotope answer
                                $ssa = $all_mols_src[$als["origin"]["molecule"]][$all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->connectedObjs[1]];
                                //Target bond first object isotope answer
                                $afa = $all_mols_target[$alt["origin"]["molecule"]][$all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->connectedObjs[0]];
                                //Target bond second object isotope answer
                                $asa = $all_mols_target[$alt["origin"]["molecule"]][$all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->connectedObjs[1]];

                                if ((($sfa->isotopeId == $afa->isotopeId) && ($ssa->isotopeId == $asa->isotopeId)) || (($sfa->isotopeId == $asa->isotopeId) && ($ssa->isotopeId == $afa->isotopeId)) ) {
                                    
                                    //Covalente list aroud the points which are themself surrounding the selected covalent
                                    $covalentListOriginalFirst = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->connectedObjs[0], $all_mols_src[$als["origin"]["molecule"]]);
                                    $covalentListOriginalSecond = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->connectedObjs[1] ,$all_mols_src[$als["origin"]["molecule"]]);
                                    
                                    $covalentListTargetFirst = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->connectedObjs[0] ,$all_mols_target[$alt["origin"]["molecule"]]);
                                    $covalentListTargetSecond = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->connectedObjs[1] ,$all_mols_target[$alt["origin"]["molecule"]]);
                                    
                                    $idOriginal1 = $all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->connectedObjs[0];
                                    $idOriginal2 = $all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]->connectedObjs[1];
                                    
                                    $idTarget1 = $all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->connectedObjs[0];
                                    $idTarget2 = $all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]->connectedObjs[1];
                                    
                                    //We unset the allready checked bond
                                    unset($all_mols_src[$als["origin"]["molecule"]][$als["origin"]["point"]]);
                                    unset($all_mols_target[$alt["origin"]["molecule"]][$alt["origin"]["point"]]);
                                    
                                    unset($covalentListOriginalFirst[$als["origin"]["point"]]);
                                    unset($covalentListOriginalSecond[$als["origin"]["point"]]);       
                                    
                                    unset($covalentListTargetFirst[$alt["origin"]["point"]]);
                                    unset($covalentListTargetSecond[$alt["origin"]["point"]]);      
                                    
                                    /************** testing path for points of arrow origin **************/
                                    //Compare the first point of covalent STUDENT with first of covalent teacher answer
                                    $ans1 = qtype_kekule_chem_utils::compare_path($idOriginal1, $idTarget1, $all_mols_src[$als["origin"]["molecule"]], $all_mols_target[$alt["origin"]["molecule"]], $covalentListOriginalFirst, $covalentListTargetFirst);

                                    if (!$ans1) {
                                        // if the points are reversed check in other order
                                        $ans11 = qtype_kekule_chem_utils::compare_path($idOriginal1, $idTarget2, $all_mols_src[$als["origin"]["molecule"]], $all_mols_target[$alt["origin"]["molecule"]], $covalentListOriginalFirst, $covalentListTargetSecond);
                                        
                                        //the points are well reversed 
                                        if ($ans11 == true) {
                                             $ans22 = qtype_kekule_chem_utils::compare_path($idOriginal2, $idTarget1, $all_mols_src[$als["origin"]["molecule"]], $all_mols_target[$alt["origin"]["molecule"]], $covalentListOriginalSecond, $covalentListTargetFirst);
                                             $answer1 = $ans11 && $ans22;
                                        }
                                        
                                    } else { //it's shire the points are not reversed
                                        //Compare the second point of covalent STUDENT with second of covalent teacher answer
                                        $ans2 = qtype_kekule_chem_utils::compare_path($idOriginal2, $idTarget2, $all_mols_src[$als["origin"]["molecule"]], $all_mols_target[$alt["origin"]["molecule"]], $covalentListOriginalSecond, $covalentListTargetSecond);
                                        $answer1 = $ans1 && $ans2;
                                    }
                                }
                                   
                            }
                        }
                    }
                    
                    $answer2 = false;
                    
                    //Check the molecule target of the arrow
                    if ($all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->__type__ == "Kekule.Atom") {
                        //Check target istope
                        $is_target_iso_the_same = $all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->isotopeId == $all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->isotopeId;
                        
                        if ($is_target_iso_the_same) {
                            $covalentListOriginal = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($als["target"]["point"],$all_mols_src_2[$als["target"]["molecule"]]);
                            $covalentListTarget = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($alt["target"]["point"],$all_mols_target_2[$alt["target"]["molecule"]]);
                            $answer2 = qtype_kekule_chem_utils::compare_path($als["target"]["point"],$alt["target"]["point"],$all_mols_src_2[$als["target"]["molecule"]],$all_mols_target_2[$alt["target"]["molecule"]], $covalentListOriginal, $covalentListTarget);
                        } 
                    } else {
                        if ($all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->bondType == "covalent") {
                            //check bondorder
                            $bond_same = $all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->bondOrder == $all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->bondOrder;
                            
                            if ($bond_same == true) {
                            /*Check Connected Objects nature*/
                            //Src bond first object isotope student answer
                            $sfa = $all_mols_src_2[$als["target"]["molecule"]][$all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->connectedObjs[0]];                            
                            //Src bond second object isotope answer
                            $ssa = $all_mols_src_2[$als["target"]["molecule"]][$all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->connectedObjs[1]];
                            //Target bond first object isotope answer
                            $afa = $all_mols_target_2[$alt["target"]["molecule"]][$all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->connectedObjs[0]];
                            //Target bond second object isotope answer
                            $asa = $all_mols_target_2[$alt["target"]["molecule"]][$all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->connectedObjs[1]];
                            
                                if ((($sfa->isotopeId == $afa->isotopeId) && ($ssa->isotopeId == $asa->isotopeId)) || (($sfa->isotopeId == $asa->isotopeId) && ($ssa->isotopeId == $afa->isotopeId)) ) {
                                                                        //Covalente list aroud the points which are themself surrounding the selected covalent
                                    $covalentListOriginalFirst = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->connectedObjs[0], $all_mols_src_2[$als["target"]["molecule"]]);
                                    $covalentListOriginalSecond = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->connectedObjs[1] ,$all_mols_src_2[$als["target"]["molecule"]]);

                                    $covalentListTargetFirst = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->connectedObjs[0] ,$all_mols_target_2[$alt["target"]["molecule"]]);
                                    $covalentListTargetSecond = qtype_kekule_chem_utils::find_covalent_connected_to_a_point($all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->connectedObjs[1] ,$all_mols_target_2[$alt["target"]["molecule"]]);

                                    $idOriginal1 = $all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->connectedObjs[0];
                                    $idOriginal2 = $all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]->connectedObjs[1];

                                    $idTarget1 = $all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->connectedObjs[0];
                                    $idTarget2 = $all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]->connectedObjs[1];

                                    //We unset the allready checked bond
                                    unset($all_mols_src_2[$als["target"]["molecule"]][$als["target"]["point"]]);
                                    unset($all_mols_target_2[$alt["target"]["molecule"]][$alt["target"]["point"]]);

                                    unset($covalentListOriginalFirst[$als["target"]["point"]]);
                                    unset($covalentListOriginalSecond[$als["target"]["point"]]);       

                                    unset($covalentListTargetFirst[$alt["target"]["point"]]);
                                    unset($covalentListTargetSecond[$alt["target"]["point"]]);      

                                    /************** testing path for points of arrow origin **************/
                                    //Compare the first point of covalent STUDENT with first of covalent teacher answer
                                    $ans1 = qtype_kekule_chem_utils::compare_path($idOriginal1, $idTarget1, $all_mols_src_2[$als["target"]["molecule"]], $all_mols_target_2[$alt["target"]["molecule"]], $covalentListOriginalFirst, $covalentListTargetFirst);

                                    if (!$ans1) {
                                        // if the points are reversed check in other order
                                        $ans11 = qtype_kekule_chem_utils::compare_path($idOriginal1, $idTarget2, $all_mols_src_2[$als["target"]["molecule"]], $all_mols_target_2[$alt["target"]["molecule"]], $covalentListOriginalFirst, $covalentListTargetSecond);

                                        //the points are well reversed 
                                        if ($ans11 == true) {
                                             $ans22 = qtype_kekule_chem_utils::compare_path($idOriginal2, $idTarget1, $all_mols_src_2[$als["target"]["molecule"]], $all_mols_target_2[$alt["target"]["molecule"]], $covalentListOriginalSecond, $covalentListTargetFirst);
                                             $answer2 = $ans11 && $ans22;
                                        }

                                    } else { //it's shire the points are not reversed
                                        //Compare the second point of covalent STUDENT with second of covalent teacher answer
                                        $ans2 = qtype_kekule_chem_utils::compare_path($idOriginal2, $idTarget2, $all_mols_src_2[$als["target"]["molecule"]], $all_mols_target_2[$alt["target"]["molecule"]], $covalentListOriginalSecond, $covalentListTargetSecond);
                                        $answer2 = $ans1 && $ans2;
                                    }
                                }
                            }
                        }
                    }
                    
                                            
                    if ($answer2 && $answer1) {
                        $countCorrespondingArrows++;
                        unset ($arrow_list_target[$id]);
                    }

                  /*  if ($answer1 && $is_target_iso_the_same) {
                        break;
                    }*/
                } /*else {
                    return false;
                }*/
                /************************/
                
                
            }
            
        }
        
        if ($countCorrespondingArrows == sizeof($arrow_list_src)) {
            return true;
        } else {
            return false;
        }
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
