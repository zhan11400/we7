<?php

/*
 * 人人商城
 *
 * 青岛易联互动网络科技有限公司
 * http://www.we7shop.cn
 * TEL: 4000097827/18661772381/15865546761
 */
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Util_EweiShopV2Model {

    public function getExpressList($express, $expresssn,$mobile) {
        global $_W;

        $express_set = $_W['shopset']['express'];

        $express = $express=="jymwl" ? "jiayunmeiwuliu" : $express;
        $express = $express=="TTKD" ? "tiantian" : $express;
        $express = $express=="jjwl" ? "jiajiwuliu" : $express;
        $express = $express=="zhongtiekuaiyun" ? "ztky" : $express;

        load()->func('communication');
        // 如果开启正式接口 则先调用正式接口
        if(!empty($express_set['isopen']) && !empty($express_set['apikey'])){
            if(!empty($express_set['cache']) && $express_set['cache']>0){
                // 查询缓存 如果缓存中有&&没过期直接使用
                $cache_time = $express_set['cache'] * 60;
                $cache = pdo_fetch("SELECT * FROM".tablename("ewei_shop_express_cache")."WHERE express=:express AND expresssn=:expresssn LIMIT 1", array('express'=>$express, 'expresssn'=>$expresssn));
                if($cache['lasttime']+$cache_time>=time() && !empty($cache['datas'])){
                    return iunserializer($cache['datas']);
                }
            }
            if($express_set['isopen']==1){
                $url = "http://api.kuaidi100.com/api?id={$express_set['apikey']}&com={$express}&num={$expresssn}";
                $params = array();
            }else{
                $url = "http://poll.kuaidi100.com/poll/query.do";
                $params = array('customer' => $express_set['customer'], 'param' => json_encode(array('com' => $express, 'num' => $expresssn)));
                $params['sign'] = md5($params["param"].$express_set['apikey'].$params["customer"]);
                $params['sign'] = strtoupper($params["sign"]);
                $params['phone'] = $mobile;
            }
            $response = ihttp_post($url, $params);
            $content = $response['content'];
            $info = json_decode($content, true);
        }

        // 未开启正式接口或者正式接口返回数据错误则调用默认接口
        if(!isset($info) || empty($info['data']) || !is_array($info['data'])) {
            $url = "https://www.kuaidi100.com/query?type={$express}&postid={$expresssn}&id=1&valicode=&temp=";
            $response = ihttp_request($url);
            $content = $response['content'];
            $info = json_decode($content, true);
            $useapi = false;
        }else{
            $useapi = true;
        }

        $list = array();

        if(!empty($info['data']) && is_array($info['data'])){
            foreach ($info['data'] as $index=>$data){
                $list[] = array(
                    'time' => trim($data['time']),
                    'step' => trim($data['context'])
                );
            }
        }

        if($useapi && $express_set['cache']>0 && !empty($list)){
            // 更新缓存
            if(empty($cache)){
                pdo_insert("ewei_shop_express_cache", array('expresssn'=>$expresssn, 'express'=>$express, 'lasttime'=>time(), 'datas'=>iserializer($list)));
            }else{
                pdo_update("ewei_shop_express_cache", array('lasttime'=>time(), 'datas'=>iserializer($list)), array('id'=>$cache['id']));
            }
        }

        return $list;
    }

    // 根据IP获取城市
    function getIpAddress() {
        $ipContent = file_get_contents("http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js");
        $jsonData = explode("=", $ipContent);
        $jsonAddress = substr($jsonData[1], 0, -1);
        return $jsonAddress;
    }

    function checkRemoteFileExists($url) {
        $curl = curl_init($url);
        //不取回数据
        curl_setopt($curl, CURLOPT_NOBODY, true);
        //发送请求
        $result = curl_exec($curl);
        $found = false;
        if ($result !== false) {
            //检查http响应码是否为200
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($statusCode == 200) {
                $found = true;
            }
        }
        curl_close($curl);
        return $found;
    }

    /**
     * 计算两组经纬度坐标 之间的距离
     * params ：lat1 纬度1； lng1 经度1； lat2 纬度2； lng2 经度2； len_type （1:m or 2:km);
     * return m or km
     */
    function GetDistance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2)
    {
        $pi = 3.1415926;
        $er = 6378.137;

        $radLat1 = $lat1 * $pi / 180.0;
        $radLat2 = $lat2 * $pi / 180.0;
        $a = $radLat1 - $radLat2;
        $b = ($lng1 * $pi / 180.0) - ($lng2 * $pi / 180.0);
        $s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
        $s = $s * $er;
        $s = round($s * 1000);
        if ($len_type > 1)
        {
            $s /= 1000;
        }
        return round($s, $decimal);
    }

    function multi_array_sort($multi_array, $sort_key, $sort = SORT_ASC){
        if(is_array($multi_array)){
            foreach ($multi_array as $row_array){
                if(is_array($row_array)){
                    $key_array[] = $row_array[$sort_key];
                }else{
                    return false;
                }
            }
        }else{
            return false;
        }

        array_multisort($key_array, $sort , $multi_array);

        return $multi_array;
    }


    function get_area_config_data($uniacid = 0){
        global $_W;

        if (empty($uniacid)) {
            $uniacid = $_W['uniacid'];
        }
        $sql = 'select * from '. tablename('ewei_shop_area_config').' where uniacid=:uniacid limit 1';
        $data = pdo_fetch($sql, array(':uniacid'=>$uniacid));


        return $data;

    }

    function get_area_config_set(){
        global $_W;

        $data = m('common')->getSysset('area_config');
        if (empty($data)) {
            $data = $this->get_area_config_data();

        }
        return $data;

    }

    function pwd_encrypt($string, $operation, $key='key'){
        $key=md5($key);
        $key_length=strlen($key);
        $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
        $string_length=strlen($string);
        $rndkey=$box=array();
        $result='';
        for($i=0;$i<=255;$i++){
            $rndkey[$i]=ord($key[$i%$key_length]);
            $box[$i]=$i;
        }
        for($j=$i=0;$i<256;$i++){
            $j=($j+$box[$i]+$rndkey[$i])%256;
            $tmp=$box[$i];
            $box[$i]=$box[$j];
            $box[$j]=$tmp;
        }
        for($a=$j=$i=0;$i<$string_length;$i++){
            $a=($a+1)%256;
            $j=($j+$box[$a])%256;
            $tmp=$box[$a];
            $box[$a]=$box[$j];
            $box[$j]=$tmp;
            $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
        }
        if($operation=='D'){
            if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){
                return substr($result,8);
            }else{
                return'';
            }
        }else{
            return str_replace('=','',base64_encode($result));
        }
    }

    function location($lat, $lng){

        $newstore_plugin = p('newstore');
        if ($newstore_plugin) {
            $newstore_data = m('common')->getPluginset('newstore');
            $key = $newstore_data['baidukey'];
        }

        if (empty($key)) {
            $key = 'ZQiFErjQB7inrGpx27M1GR5w3TxZ64k7';
        }

        $url = "http://api.map.baidu.com/geocoder/v2/?callback=renderReverse&location=".$lat.",".$lng."&output=json&pois=1&ak=" . $key;

        $fileContents = file_get_contents($url);
        $contents = ltrim($fileContents, 'renderReverse&&renderReverse(');
        $contents = rtrim($contents, ')');
        $data =  json_decode($contents,true);
        return $data;
    }

    //通过地址信息获取坐标（高德）
    function geocode($address,$key=0){
        if(empty($key)){
            $key = '7e56a024f468a18537829cb44354739f';
        }
        $address = str_replace(' ','',$address);
        $url = "http://restapi.amap.com/v3/geocode/geo?address=".$address."&key=" . $key;
        $contents = file_get_contents($url);
        $data =  json_decode($contents,true);
        return $data;
    }
}
