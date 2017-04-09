<?php

/* 
 * Proses mining function
 */

function reset_temporary($db_object){
    $sql1 = "TRUNCATE itemset1";
    $db_object->db_query($sql1);
    
    $sql2 = "TRUNCATE itemset2";
    $db_object->db_query($sql2);
    
    $sql3 = "TRUNCATE itemset3";
    $db_object->db_query($sql3);
    
    $sql4 = "TRUNCATE confidence";
    $db_object->db_query($sql4);
}

function is_exist_variasi_itemset($array_item1, $array_item2, $item1, $item2) {
    $return = true;

    $bool1 = in_array($item2, $array_item1);
    $bool2 = in_array($item1, $array_item2);
    if (!$bool1 || !$bool2) {
        $return = false;
    }
    return $return;
}


function mining_process($db_object, $min_support, $min_confidence, $start_date, $end_date, $id_process){
    //remove reset truncate (change to log mode)
    //reset_temporary($db_object);
    
    $sql = "SELECT transaction_date FROM transaksi "
            . " WHERE  transaction_date BETWEEN '$start_date' AND '$end_date' "
            . " GROUP BY transaction_date";
    $query=$db_object->db_query($sql);
    $jumlah_transaksi=$db_object->db_num_rows($query);
    
    //bulid itemset1
    $sql1 = "SELECT
            produk,
            COUNT(produk) AS jml,
            (COUNT(produk) / $jumlah_transaksi) * 100 AS support
          FROM
            transaksi
          WHERE produk != ''
          AND transaction_date BETWEEN '$start_date' AND '$end_date' 
          GROUP BY produk";
    $query1=$db_object->db_query($sql1);
    $itemset1 = array();
    echo "<strong>Itemset 1:</strong>
            <table class = 'table table-bordered table-striped  table-hover'>
            <tr>
            <th>No</th>
            <th>Item</th>
            <th>Jumlah</th>
            <th>Support</th>
            <th></th>
            </tr>";
    $no = 1;
    while($row = $db_object->db_fetch_array($query1)){
        $produk = $row['produk'];
        $jml = $row['jml'];
        $support = $row['support'];
        $lolos = ($support >= $min_support)? 1:0;
        $field_value = array(
                        "atribut"=>($produk),
                        "jumlah"=>$jml,
                        "support"=>$support,
                        "lolos"=>$lolos,
                        "id_process"=>$id_process
                    );
        $query = $db_object->insert_record("itemset1", $field_value);
        
        if($lolos){
            $itemset1[] = $produk;
        }
        echo "<tr>";
        echo "<td>" . $no . "</td>";
        echo "<td>" . $produk . "</td>";
        echo "<td>" . $jml . "</td>";
        echo "<td>" . price_format($support) . "</td>";
        echo "<td>" . ($lolos == 1 ? "Lolos" : "Tidak Lolos") . "</td>";
        echo "</tr>";
        $no++;
    }
    echo "</table>";
    
    //build itemset2
    $a = 0;
    $NilaiAtribut1 = $NilaiAtribut2 = array();
    $itemset2_var1 = $itemset2_var2 = array();
    echo "<strong>Itemset 2:</strong>
            <table class='table table-bordered table-striped  table-hover'>
                <tr>
                    <th>No</th>
                    <th>Item 1</th>
                    <th>Item 2</th>
                    <th>Jumlah</th>
                    <th>Support</th>
                    <th></th>
                </tr>";
    $no=1;
    while ($a <= count($itemset1)) {
        $b = 0;
        while ($b <= count($itemset1)) {
            $variance1 = $itemset1[$a];
            $variance2 = $itemset1[$b];
            if (!empty($variance1) && !empty($variance2)) {
                if ($variance1 != $variance2) {
                    if(!is_exist_variasi_itemset($NilaiAtribut1, $NilaiAtribut2, $variance1, $variance2)) {
                        $jml_itemset2 = get_count_itemset2($db_object, $variance1, $variance2, $start_date, $end_date);
                        $NilaiAtribut1[] = $variance1;
                        $NilaiAtribut2[] = $variance2;

                        $support2 = ($jml_itemset2/$jumlah_transaksi) * 100;
                        $lolos = ($support2 >= $min_support)? 1:0;
                        //masukkan ke table itemset2
                        $db_object->insert_record("itemset2", 
                        array("atribut1" => $variance1,
                                "atribut2" => $variance2,
                                "jumlah" => $jml_itemset2,
                                "support" => $support2,
                                "lolos" => $lolos,
                                "id_process"=>$id_process
                        ));     
                        
                        if($lolos){
                            $itemset2_var1[] = $variance1;
                            $itemset2_var2[] = $variance2;
                        }
                        
                        echo "<tr>";
                        echo "<td>" . $no . "</td>";
                        echo "<td>" . $variance1 . "</td>";
                        echo "<td>" . $variance2 . "</td>";
                        echo "<td>" . $jml_itemset2 . "</td>";
                        echo "<td>" . price_format($support2) . "</td>";
                        echo "<td>" . ($lolos == 1 ? "Lolos" : "Tidak Lolos") . "</td>";
                        echo "</tr>";
                        $no++;
                    }
                }
            }
            $b++;
        }
        $a++;
    }
    echo "</table>";
    
    //build itemset3
    $a = 0;
    $tigaVariasiItem = array();
    echo "<strong>Itemset 3:</strong>
    <table class='table table-bordered table-striped  table-hover'>
        <tr>
            <th>No</th>
            <th>Item 1</th>
            <th>Item 2</th>
            <th>Item 3</th>
            <th>Jumlah</th>
            <th>Support</th>
            <th></th>
        </tr>";
    $no=1;
    while ($a <= count($itemset2_var1)) {
        $b = 0;
        while ($b <= count($itemset2_var1)) {
            if($a != $b){
                $itemset1a = $itemset2_var1[$a];
                $itemset1b = $itemset2_var1[$b];

                $itemset2a = $itemset2_var2[$a];
                $itemset2b = $itemset2_var2[$b];

                if (!empty($itemset1a) && !empty($itemset1b)&& !empty($itemset2a) && !empty($itemset2b)) {
                    $temp_array = get_variasi_itemset3($tigaVariasiItem, 
                            $itemset1a, $itemset1b, $itemset2a, $itemset2b);
                    
                    if(count($temp_array)>0){
                        //variasi-variasi itemset isi ke array
                        $tigaVariasiItem = array_merge($tigaVariasiItem, $temp_array);
                        
                        foreach ($temp_array as $idx => $val_nilai) {
                            $itemset1 = $itemset2 = $itemset3 = "";
                            
                            $aaa=0;
                            foreach ($val_nilai as $idx1 => $v_nilai) {
                                if($aaa==0){
                                    $itemset1 = $v_nilai;
                                }
                                if($aaa==1){
                                    $itemset2 = $v_nilai;
                                }
                                if($aaa==2){
                                    $itemset3 = $v_nilai;
                                }
                                $aaa++;
                            }
                            
                            //jumlah item set3 dan menghitung supportnya
                            $jml_itemset3 = get_count_itemset3($db_object, $itemset1, $itemset2, $itemset3, $start_date, $end_date);
                            $support3 = ($jml_itemset3/$jumlah_transaksi) * 100;
                            $lolos = ($support3 >= $min_support)? 1:0;
                            //masukkan ke table itemset3
                            $db_object->insert_record("itemset3", array("atribut1" => $itemset1,
                                "atribut2" => $itemset2,
                                "atribut3" => $itemset3,
                                "jumlah" => $jml_itemset3,
                                "support" => $support3,
                                "lolos" => $lolos,
                                "id_process" => $id_process
                            ));
                            
                            echo "<tr>";
                            echo "<td>" . $no . "</td>";
                            echo "<td>" . $itemset1. "</td>";
                            echo "<td>" . $itemset2 . "</td>";
                            echo "<td>" . $itemset3 . "</td>";
                            echo "<td>" . $jml_itemset3 . "</td>";
                            echo "<td>" . price_format($support3) . "</td>";
                            echo "<td>" . ($lolos == 1 ? "Lolos" : "Tidak Lolos") . "</td>";
                            echo "</tr>";
                            $no++;
                        }
                    }
                    
                }
            }
            $b++;
        }
        $a++;
    }
    echo "</table>";


    //hitung confidence
    $confidence_from_itemset = 0;
    //dari itemset 3 jika tidak ada yg lolos ambil dari itemset 2 jika tiak ada gagal mendapatkan confidence
    $sql_3 = "SELECT * FROM itemset3 WHERE lolos = 1 AND id_process = ".$id_process;
    $res_3 = $db_object->db_query($sql_3);
    $jumlah_itemset3_lolos = $db_object->db_num_rows($res_3);
    if($jumlah_itemset3_lolos > 0){
        $confidence_from_itemset = 3;
        
        while($row_3 = $db_object->db_fetch_array($res_3)){
            $atribut1 = $row_3['atribut1'];
            $atribut2 = $row_3['atribut2'];
            $atribut3 = $row_3['atribut3'];
            $supp_xuy = $row_3['support'];
            
            //1,2 => 3
            hitung_confidence($db_object, $supp_xuy, $min_support, $min_confidence, 
                    $atribut1, $atribut2, $atribut3, $id_process);
            
            //1,3 => 2
            hitung_confidence($db_object, $supp_xuy, $min_support, $min_confidence, 
                    $atribut1, $atribut3, $atribut2, $id_process);
            
            //2,3 => 1
            hitung_confidence($db_object, $supp_xuy, $min_support, $min_confidence, 
                    $atribut1, $atribut3, $atribut2, $id_process);
            
            //1 => 2,3
            hitung_confidence1($db_object, $supp_xuy, $min_support, $min_confidence, 
                    $atribut1, $atribut2, $atribut3, $id_process);
            
            //2 => 1,3
            hitung_confidence1($db_object, $supp_xuy, $min_support, $min_confidence,
                    $atribut2, $atribut1, $atribut3, $id_process);
            
            //3 => 1,2
            hitung_confidence1($db_object, $supp_xuy, $min_support, $min_confidence,
                    $atribut3, $atribut1, $atribut2, $id_process);
            
        }
    }

    //dari itemset 2
    $sql_2 = "SELECT * FROM itemset2 WHERE lolos = 1 AND id_process = ".$id_process;
    $res_2 = $db_object->db_query($sql_2);
    $jumlah_itemset2_lolos = $db_object->db_num_rows($res_2);
    if($jumlah_itemset2_lolos > 0){
        $confidence_from_itemset = 2;
        while($row_2 = $db_object->db_fetch_array($res_2)){
            $atribut1 = $row_2['atribut1'];
            $atribut2 = $row_2['atribut2'];
            $supp_xuy = $row_2['support'];
            
            //1 => 2
            hitung_confidence2($db_object, $supp_xuy, $min_support, $min_confidence, $atribut1, $atribut2, $id_process);
            
            //2 => 1
            hitung_confidence2($db_object, $supp_xuy, $min_support, $min_confidence, $atribut2, $atribut1, $id_process);
        }
    }

    if($confidence_from_itemset==0){
        return false;
    }

    return true;
}


function get_variasi_itemset3($array_itemset3, $item1, $item2, $item3, $item4) {
    $return = array();
    
    $return1 = array();
    if(!in_array($return1, $item1)){
        $return1[] = $item1;
    }
    if(!in_array($return1, $item2)){
        $return1[] = $item2;
    }
    if(!in_array($return1, $item3)){
        $return1[] = $item3;
    }
    
    $return2 = array();
    if(!in_array($return2, $item1)){
        $return2[] = $item1;
    }
    if(!in_array($return2, $item2)){
        $return2[] = $item2;
    }
    if(!in_array($return2, $item4)){
        $return2[] = $item4;
    }
    
    $return3 = array();
    if(!in_array($return3, $item1)){
        $return3[] = $item1;
    }
    if(!in_array($return3, $item3)){
        $return3[] = $item3;
    }
    if(!in_array($return3, $item4)){
        $return3[] = $item4;
    }
    
    $return4 = array();
    if(!in_array($return4, $item2)){
        $return4[] = $item2;
    }
    if(!in_array($return4, $item3)){
        $return4[] = $item3;
    }
    if(!in_array($return4, $item4)){
        $return4[] = $item4;
    }
    
    if(count($return1)==3){
        if(!is_exist_variasi_on_itemset3($return, $return1)){
            if(!is_exist_variasi_on_itemset3($array_itemset3, $return1)){
                $return[] = $return1;
            }
        }
    }
    if(count($return2)==3){
        if(!is_exist_variasi_on_itemset3($return, $return2)){
            if(!is_exist_variasi_on_itemset3($array_itemset3, $return2)){
                $return[] = $return2;
            }
        }
    }
    if(count($return3)==3){
        if(!is_exist_variasi_on_itemset3($return, $return3)){
            if(!is_exist_variasi_on_itemset3($array_itemset3, $return3)){
                $return[] = $return3;
            }
        }
    }
    if(count($return4)==3){
        if(!is_exist_variasi_on_itemset3($return, $return4)){
            if(!is_exist_variasi_on_itemset3($array_itemset3, $return4)){
                $return[] = $return4;
            }
        }
    }
    return $return;
}

function is_exist_variasi_on_itemset3($array, $tiga_variasi){
    $return = false;
    
    foreach ($array as $key => $value) {
        $jml=0;
        foreach ($value as $key1 => $val1) {
            foreach ($tiga_variasi as $key2 => $val2) {
                if($val1 == $val2){
                    $jml++;
                }
            }
        }
        if($jml==3){
            $return=true;
            break;
        }
    }
    
    return $return;
}


function get_count_itemset2($db_object, $atribut1, $atribut2, $start_date, $end_date) {
    $sql = "SELECT COUNT(transaction_date) AS jml, transaction_date 
            FROM transaksi 
            WHERE (produk='$atribut1' OR produk = '$atribut2') 
                AND transaction_date BETWEEN '$start_date' AND '$end_date' 
            GROUP BY transaction_date
            HAVING COUNT(transaction_date)=2";
    $result = $db_object->db_query($sql);
    $jml = $db_object->db_num_rows($result);
    return $jml;
}

function get_count_itemset3($db_object, $atribut1, $atribut2, $atribut3, $start_date, $end_date) {
    $sql = "SELECT COUNT(transaction_date) AS jml, transaction_date FROM transaksi 
            WHERE (produk='$atribut1' OR produk = '$atribut2'  OR produk = '$atribut3') 
                AND transaction_date BETWEEN '$start_date' AND '$end_date' 
            GROUP BY transaction_date
            HAVING COUNT(transaction_date)=3";
    $result = $db_object->db_query($sql);
    $jml = $db_object->db_num_rows($result);
    return $jml;
}


/**
 * kombinasi atibut1 U atribut2 => $atribut3
 * save to table confidence
 * @param type $db_object
 * @param type $supp_xuy
 * @param type $atribut1
 * @param type $atribut2
 * @param type $atribut3
 */
function hitung_confidence($db_object, $supp_xuy, $min_support, $min_confidence,
        $atribut1, $atribut2, $atribut3, $id_process){
    
    $sql1_ = "SELECT support FROM itemset2 "
            . " WHERE atribut1 = '".$atribut1."' "
            . " AND atribut2 = '".$atribut2."' "
            . " AND id_process = ".$id_process;
    $res1_ = $db_object->db_query($sql1_);
    while($row1_ = $db_object->db_fetch($res1_)){
        $kombinasi1 = $atribut1." , ".$atribut2;
        $kombinasi2 = $atribut3;
        $supp_x = $row1_['support'];
        $conf = ($supp_xuy/$supp_x)*100;
        //lolos seleksi min confidence itemset3
        $lolos = ($conf >= $min_confidence)? 1:0;
        //masukkan ke table confidence
        $db_object->insert_record("confidence", 
                array("kombinasi1" => $kombinasi1,
                    "kombinasi2" => $kombinasi2,
                    "support_xUy" => $supp_xuy,
                    "support_x" => $supp_x,
                    "confidence" => $conf,
                    "lolos" => $lolos,
                    "min_support" => $min_support,
                    "min_confidence" => $min_confidence,
                    "id_process" => $id_process
                ));
    }
}


/**
 * confidence atribut1 => atribut2 U atribut3
 * @param type $db_object
 * @param type $supp_xuy
 * @param type $min_support
 * @param type $min_confidence
 * @param type $atribut1
 * @param type $atribut2
 * @param type $atribut3
 */
function hitung_confidence1($db_object, $supp_xuy, $min_support, $min_confidence,
        $atribut1, $atribut2, $atribut3, $id_process){
    
        $sql4_ = "SELECT support FROM itemset1 "
                . " WHERE atribut = '".$atribut1."' "
                . " AND id_process = ".$id_process;
        $res4_ = $db_object->db_query($sql4_);
        while($row4_ = $db_object->db_fetch($res4_)){
            $kombinasi1 = $atribut1;
            $kombinasi2 = $atribut2." , ".$atribut3;
            $supp_x = $row4_['support'];
            $conf = ($supp_xuy/$supp_x)*100;
            //lolos seleksi min confidence itemset3
            $lolos = ($conf >= $min_confidence)? 1:0;
            //masukkan ke table confidence
            $db_object->insert_record("confidence", 
                    array("kombinasi1" => $kombinasi1,
                        "kombinasi2" => $kombinasi2,
                        "support_xUy" => $supp_xuy,
                        "support_x" => $supp_x,
                        "confidence" => $conf,
                        "lolos" => $lolos,
                        "min_support" => $min_support,
                        "min_confidence" => $min_confidence,
                        "id_process" => $id_process
                    ));
        }
}


function hitung_confidence2($db_object, $supp_xuy, $min_support, $min_confidence,
        $atribut1, $atribut2, $id_process){
    
        $sql1_ = "SELECT support FROM itemset1 "
                    . " WHERE atribut = '".$atribut1."' AND id_process = ".$id_process;
        $res1_ = $db_object->db_query($sql1_);
        while($row1_ = $db_object->db_fetch_array($res1_)){
            $kombinasi1 = $atribut1;
            $kombinasi2 = $atribut2;
            $supp_x = $row1_['support'];
            $conf = ($supp_xuy/$supp_x)*100;
            //lolos seleksi min confidence itemset3
            $lolos = ($conf >= $min_confidence)? 1:0;
            //masukkan ke table confidence
            $db_object->insert_record("confidence", 
                    array("kombinasi1" => $kombinasi1,
                        "kombinasi2" => $kombinasi2,
                        "support_xUy" => $supp_xuy,
                        "support_x" => $supp_x,
                        "confidence" => $conf,
                        "lolos" => $lolos,
                        "min_support" => $min_support,
                        "min_confidence" => $min_confidence,
                        "id_process" => $id_process
                    ));
        }
}