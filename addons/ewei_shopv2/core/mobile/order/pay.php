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


class Pay_EweiShopV2Page extends MobileLoginPage
{

    function main()
    {
        global $_W, $_GPC;
        $openid = $_W['openid'];

        $uniacid = $_W['uniacid'];
        $member = m('member')->getMember($openid, true);
        $orderid = intval($_GPC['id']);

        //代付开关
        $peerPaySwi = m('common')->getPluginset('sale');
        $peerPaySwi = $peerPaySwi['peerpay']['open'];
        //代付
        $ispeerpay = m('order')->checkpeerpay($orderid);//检查是否是代付订单

        if(!empty($order['istrade'])) {
            $ispeerpay = 0;
        }

        if (!empty($ispeerpay)){//如果是代付订单
            //解决单人代付时,多人同时点击多次支付的问题
//            $open_redis = function_exists('redis') && !is_error(redis());
//            if( $open_redis ) {
//                $redis_key = "{$_W['uniacid']}_peerpay_order__pay_{$ispeerpay['id']}";
//                $redis = redis();
//                if (!is_error($redis)) {
//                    if($redis->get($redis_key)) {
//                        $this->message('当前有用户正在支付,请稍后再试');
//                    }
//                    $redis->setex($redis_key, 60,time());
//                }
//            }

            if (pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename('ewei_shop_order_peerpay_payinfo')." WHERE openid = :openid AND pid = :pid",array(':openid'=>$_W['openid'],':pid'=>$ispeerpay['id']))){
                $this->message('每人只能代付一次');
            }
            $peerpayMessage = trim($_GPC['peerpaymessage']);
            $peerpay_info = (float)pdo_fetchcolumn("select SUM(price) from " . tablename('ewei_shop_order_peerpay_payinfo') . ' where pid=:pid limit 1', array(':pid' => $ispeerpay['id']));
            $peerprice = floatval($_GPC['peerprice']);
            if (empty($peerprice) || $peerprice<=0){
                header('Location:'.mobileUrl('order/pay/peerpayshare',array('id'=>$orderid)));
                exit();
            }else{
                $openid = pdo_fetchcolumn("SELECT openid FROM ".tablename('ewei_shop_order')." WHERE id = :id AND uniacid = :uniacid LIMIT 1",array(':id'=>$orderid,':uniacid'=>$_W['uniacid']));
            }
        }

        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));
        $og_array = m('order')->checkOrderGoods($orderid);
        if (!empty($og_array['flag'])) {
            $this->message($og_array['msg'], '', 'error');
        }

        if (empty($orderid)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }

        if (empty($order)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }


        //判断是否为o2o支付
        if((empty($order['isnewstore']) || empty($order['storeid']))&&empty($order['istrade']))
        {
            $order_goods = pdo_fetchall('select og.id,g.title, og.goodsid,og.optionid,g.total as stock,og.total as buycount,g.status,g.deleted,g.maxbuy,g.usermaxbuy,g.istime,g.timestart,g.timeend,g.buylevels,g.buygroups,g.totalcnf,og.seckill from  ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id '
                . ' where og.orderid=:orderid and og.uniacid=:uniacid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $orderid));


            foreach ($order_goods as $data) {
                if (empty($data['status']) || !empty($data['deleted'])) {

                    if ($_W['ispost']) {
                        show_json(0, $data['title'] . '<br/> 已下架!');
                    } else {
                        $this->message($data['title'] . '<br/> 已下架!', mobileUrl('order'));
                    }
                }

                $unit = empty($data['unit']) ? '件' : $data['unit'];


                $seckillinfo = plugin_run("seckill::getSeckill", $data['goodsid'], $data['optionid'], true, $_W['openid']);

                if ($data['seckill']) {
                    //是秒杀的商品
                    if (empty($seckillinfo) || $seckillinfo['status'] != 0 || time() > $seckillinfo['endtime']) {
                        if ($_W['ispost']) {
                            show_json(0, $data['title'] . '<br/> 秒杀已结束，无法支付!');
                        } else {
                            $this->message($data['title'] . '<br/> 秒杀已结束，无法支付!', mobileUrl('order'));
                        }
                    }
                }

                if ($seckillinfo && $seckillinfo['status'] == 0 || !empty($ispeerpay)) {
                    //如果是秒杀，不判断任何条件//代付订单也不判断

                }else {

                    //最低购买
                    if ($data['minbuy'] > 0) {
                        if ($data['buycount'] < $data['minbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            } else {
                                $this->message($data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            }
                        }
                    }

                    //一次购买
                    if ($data['maxbuy'] > 0) {
                        if ($data['buycount'] > $data['maxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!");
                            } else {
                                $this->message($data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!", mobileUrl('order'));
                            }
                        }
                    }
                    //总购买量
                    if ($data['usermaxbuy'] > 0) {
                        $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('ewei_shop_order_goods') . ' og '
                            . ' left join ' . tablename('ewei_shop_order') . ' o on og.orderid=o.id '
                            . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                        if ($order_goodscount >= $data['usermaxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit);
                            } else {
                                $this->message($data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit, mobileUrl('order'));
                            }
                        }
                    }

                    //判断限时购
                    if ($data['istime'] == 1) {
                        if (time() < $data['timestart']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 限购时间未到!');
                            } else {
                                $this->message($data['title'] . '<br/> 限购时间未到!', mobileUrl('order'));
                            }
                        }
                        if (time() > $data['timeend']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 限购时间已过!');
                            } else {
                                $this->message($data['title'] . '<br/> 限购时间已过!', mobileUrl('order'));
                            }
                        }
                    }
                    //判断会员权限
                    if ($data['buylevels'] != '') {
                        $buylevels = explode(',', $data['buylevels']);
                        if (!in_array($member['level'], $buylevels)) {
                            if ($_W['ispost']) {
                                show_json(0, '您的会员等级无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您的会员等级无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }

                    if ($member['groupid'] == ''){
                        $groupid = array();
                    }else{
                        $groupid = explode(',',$member['groupid']);
                    }
                    //会员组权限
                    if ($data['buygroups'] != '') {
                        if(empty($groupid)){
                            $groupid[]=0;
                        }
                        $buygroups = explode(',', $data['buygroups']);
                        $groups_id = explode(',',$member['groupid']);
                        if (!array_intersect($groups_id, $buygroups)) {
                            if ($_W['ispost']) {
                                show_json(0, '您所在会员组无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您所在会员组无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }
                }
                if ($data['totalcnf'] == 1) {
                    if (!empty($data['optionid'])) {
                        $option = pdo_fetch('select id,title,marketprice,goodssn,productsn,stock,`virtual` from ' . tablename('ewei_shop_goods_option') . ' where id=:id and goodsid=:goodsid and uniacid=:uniacid  limit 1', array(':uniacid' => $uniacid, ':goodsid' => $data['goodsid'], ':id' => $data['optionid']));
                        if (!empty($option)) {
                            if ($option['stock'] != -1) {
                                if (empty($option['stock'])) {
                                    if ($_W['ispost']) {
                                        show_json(0, $data['title'] . "<br/>" . $option['title'] . " 库存不足!");
                                    } else {
                                        $this->message($data['title'] . "<br/>" . $option['title'] . " 库存不足!", mobileUrl('order'));
                                    }
                                }
                            }
                        }
                    } else {
                        if ($data['stock'] != -1) {
                            if (empty($data['stock'])) {
                                if ($_W['ispost']) {
                                    show_json(0, $data['title'] . "<br/>库存不足!");
                                } else {
                                    $this->message($data['title'] . "<br/>库存不足!", mobileUrl('order'));
                                }
                            }
                        }
                    }
                }
            }
            if(!$seckillinfo) {
                $ret = m('order')->CheckoodsStock($orderid, 1);
                if(!$ret) {
                    show_json(0, '您当前下单商品库存不足,请重新下单');
                }
            }
        }else{

            if(p('newstore')){
                $sql = "select og.id,g.title, og.goodsid,og.optionid,ng.stotal as stock,og.total as buycount,ng.gstatus as status,ng.deleted,g.maxbuy,g.usermaxbuy,g.istime,g.timestart,g.timeend,g.buylevels,g.buygroups,g.totalcnf,og.seckill,og.storeid "
                    . " from " . tablename('ewei_shop_order_goods')
                    . " og left join  " . tablename('ewei_shop_goods') . " g  on g.id=og.goodsid and g.uniacid=og.uniacid"
                    . " inner join " . tablename('ewei_shop_newstore_goods') . " ng on g.id=ng.goodsid   "
                    . " where og.orderid=:orderid and og.uniacid=:uniacid and ng.storeid=:storeid";
                $order_goods = pdo_fetchall($sql, array(':uniacid' => $uniacid, ':orderid' => $orderid,':storeid'=>$order['storeid']));

                foreach ($order_goods as $data) {
                    if (empty($data['status']) || !empty($data['deleted'])) {

                        if ($_W['ispost']) {
                            show_json(0, $data['title'] . '<br/> 已下架!');
                        } else {
                            $this->message($data['title'] . '<br/> 已下架!', mobileUrl('order'));
                        }
                    }

                    $unit = empty($data['unit']) ? '件' : $data['unit'];


                    //最低购买
                    if ($data['minbuy'] > 0) {
                        if ($data['buycount'] < $data['minbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            } else {
                                $this->message($data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            }
                        }
                    }

                    //一次购买
                    if ($data['maxbuy'] > 0) {
                        if ($data['buycount'] > $data['maxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!");
                            } else {
                                $this->message($data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!", mobileUrl('order'));
                            }
                        }
                    }
                    //总购买量
                    if ($data['usermaxbuy'] > 0) {
                        $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('ewei_shop_order_goods') . ' og '
                            . ' left join ' . tablename('ewei_shop_order') . ' o on og.orderid=o.id '
                            . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                        if ($order_goodscount >= $data['usermaxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit);
                            } else {
                                $this->message($data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit, mobileUrl('order'));
                            }
                        }
                    }


                    //判断会员权限
                    if ($data['buylevels'] != '') {
                        $buylevels = explode(',', $data['buylevels']);
                        if (!in_array($member['level'], $buylevels)) {
                            if ($_W['ispost']) {
                                show_json(0, '您的会员等级无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您的会员等级无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }

                    if ($member['groupid'] == ''){
                        $groupid = array();
                    }else{
                        $groupid = explode(',',$member['groupid']);
                    }
                    //会员组权限
                    if ($data['buygroups'] != ''){
                        if(empty($groupid)){
                            $groupid[]=0;
                        }
                        $buygroups = explode(',', $data['buygroups']);
                        $intersect = array_intersect($groupid, $buygroups);
                        if (empty($intersect)) {
                            if ($_W['ispost']) {
                                show_json(0, '您所在会员组无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您所在会员组无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }
                    if ($data['totalcnf'] == 1) {
                        if (!empty($data['optionid'])) {
                            $option = pdo_fetch('select id,marketprice,stock from ' . tablename('ewei_shop_newstore_goods_option') . ' where optionid=:optionid and goodsid=:goodsid and uniacid=:uniacid and storeid=:storeid  limit 1', array(':uniacid' => $uniacid, ':goodsid' => $data['goodsid'], ':optionid' => $data['optionid'],':storeid'=>$data['storeid']));
                            if (!empty($option)) {
                                if ($option['stock'] != -1) {
                                    if (empty($option['stock'])) {
                                        if ($_W['ispost']) {
                                            show_json(0, $data['title'] . "<br/>" . $option['title'] . " 库存不足!");
                                        } else {
                                            $this->message($data['title'] . "<br/>" . $option['title'] . " 库存不足!", mobileUrl('order'));
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($data['stock'] != -1) {
                                if (empty($data['stock'])) {
                                    if ($_W['ispost']) {
                                        show_json(0, $data['title'] . "<br/>库存不足!");
                                    } else {
                                        $this->message($data['title'] . "<br/>库存不足!", mobileUrl('order'));
                                    }
                                }
                            }
                        }
                    }
                }
            }else{
                if ($_W['ispost']) {
                    show_json(0, '门店歇业,不能付款!');
                } else {
                    $this->message("门店歇业,不能付款!", mobileUrl('order'));
                }
            }

        }

        $tradestatus = $order['tradestatus'];

        if (empty($order['istrade'])) {
            if ($order['status'] == -1) {
                header('location: ' . mobileUrl('order/detail', array('id' => $order['id'])));
                exit;
            } else if ($order['status'] >= 1) {
                header('location: ' . mobileUrl('order/detail', array('id' => $order['id'])));
                exit;
            }
        } else {
            if($order['status'] == 1 && $order['tradestatus'] == 1) {
                $order['ordersn'] = $order['ordersn_trade'];
                $order['price'] = $order['betweenprice'];
            } else if ($order['status'] == 1 && $order['tradestatus'] == 2){
                header('location: ' . mobileUrl('newstore/norder/detail', array('id' => $order['id'])));
                exit;
            } else if ($order['status'] == 0) {
                $order['price'] = $order['dowpayment'];
            }
        }


        $log = pdo_fetch('SELECT * FROM ' . tablename('core_paylog') . ' WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid limit 1', array(':uniacid' => $uniacid, ':module' => 'ewei_shopv2', ':tid' => $order['ordersn']));
        if (!empty($ispeerpay)){

        }else{
            if (!empty($log) && $log['status'] != '0') {
                if (empty($order['istrade'])) {
                    header('location: ' . mobileUrl('order/detail', array('id' => $order['id'])));
                } else {
                    header('location: ' . mobileUrl('newstore/norder/detail', array('id' => $order['id'])));
                }
                exit;
            }
        }

        //秒杀商品
        $seckill_goods = pdo_fetchall('select goodsid,optionid,seckill from  ' . tablename('ewei_shop_order_goods') . ' where orderid=:orderid and uniacid=:uniacid and seckill=1 ', array(':uniacid' => $_W['uniacid'], ':orderid' => $orderid));


        if (!empty($log) && $log['status'] == '0') {
            pdo_delete('core_paylog', array('plid' => $log['plid']));
            $log = null;
        }


        if (empty($log)) {
            $log = array(
                'uniacid' => $uniacid,
                'openid' => trim($member['uid']),
                'module' => "ewei_shopv2",
                'tid' => trim($order['ordersn']),
                'fee' => $order['price'],
                'status' => 0,
            );
            pdo_insert('core_paylog', $log);
            $plid = pdo_insertid();
        }

        $set = m('common')->getSysset(array('shop', 'pay'));
        $set['pay']['weixin'] = !empty($set['pay']['weixin_sub']) ? 1 : $set['pay']['weixin'];
        $set['pay']['weixin_jie'] = !empty($set['pay']['weixin_jie_sub']) ? 1 : $set['pay']['weixin_jie'];
        $param_title = $set['shop']['name'] . "订单";

        //是否可以余额支付
        $credit = array('success' => false);
        if (isset($set['pay']) && $set['pay']['credit'] == 1) {
            $credit = array(
                'success' => true,
                'current' => $member['credit2']
            );
        }

        $order['price'] = floatval($order['price']);
        //检测当前优惠券有没有使用过
        $plugincoupon = com('coupon');
        if ($plugincoupon) {
            $coupondata=  $plugincoupon->getCouponByDataID($order['couponid']);
            if($coupondata['used']==1){
                $this->message('出错了,此优惠券已经被使用过,请刷新页面重新下单');
            }
        }

        if (empty($order['price']) && !$credit['success']){
            header('location: ' . mobileUrl('order/pay/complete', array('id' => $order['id'],'type'=>'credit','ordersn' => $order['ordersn'])));
            exit;
        }
        //支付参数
        load()->model('payment');
        $setting = uni_setting($_W['uniacid'], array('payment'));

        $sec = m('common')->getSec();
        $sec = iunserializer($sec['sec']);

        //微信
        $wechat = array('success' => false);
        $jie = intval($_GPC['jie']);
        if (is_weixin()) {
            //微信环境

            $params = array();
            $params['tid'] = trim($log['tid']);
            if (!empty($order['ordersn2'])) {
                $var = sprintf("%02d", trim($order['ordersn2']));
                if($var>=100){
                    $var = $var+1;
                    pdo_update('ewei_shop_order', array('ordersn2' =>$var), array('id' => $orderid, 'uniacid' => $uniacid, 'openid' => $openid));
                }

                $params['tid'] .= "GJ" . $var;
            }
            $params['user'] = trim($openid);
            $params['fee'] = $order['price'];
            if (!empty($ispeerpay)){
                $params['fee'] = $peerprice;
                $params['tid'] = trim($params['tid']) . $member['id'] . str_replace('.','',$params['fee']);
                @session_start();
                $_SESSION['peerpaytid'] = trim($params['tid']);
            }
            $params['title'] = trim($param_title);

            if (isset($set['pay']) && $set['pay']['weixin'] == 1 && $jie !== 1 && !empty($set['pay']['weixin_id'])) {
                //如果开启微信支付
                $options = array();
                if (is_array($setting['payment']['wechat']) && $setting['payment']['wechat']['switch']) {
                    load()->model('payment');
                    $setting = uni_setting($_W['uniacid'], array('payment'));
                    if (is_array($setting['payment'])) {
                        $options = $setting['payment']['wechat'];
                        $options['appid'] = $_W['account']['key'];
                        $options['secret'] = $_W['account']['secret'];
                    }
                }

                $params['tid'] = substr($params['tid'], 0, 32);

                $wechat = m('common')->wechat_build($params, $options, 0);
                //file_put_contents(__DIR__."/a1.json", json_encode($wechat).PHP_EOL,8);
                if (!is_error($wechat)) {
                    $wechat['success'] = true;
                    if (!empty($wechat['code_url'])){
                        $wechat['weixin_jie'] = true;
                    }else{
                        $wechat['weixin'] = true;
                    }
                }
            }

            if ((isset($set['pay']) && $set['pay']['weixin_jie'] == 1 && !$wechat['success']) || $jie === 1) {
                //如果开启微信支付

                if (!empty($order['ordersn2'])) {
                    $params['tid'] = $params['tid'] . '_B';
                } else {
                    $params['tid'] = $params['tid'] . '_borrow';
                }

                $options = array();
                $options['appid'] = trim($sec['appid']);
                $options['mchid'] = trim($sec['mchid']);
                $options['apikey'] = trim($sec['apikey']);
                if (!empty($set['pay']['weixin_jie_sub']) && !empty($sec['sub_secret_jie_sub'])){
                    $wxuser = m('member')->wxuser($sec['sub_appid_jie_sub'],$sec['sub_secret_jie_sub']);
                    $params['openid'] = trim($wxuser['openid']);
                }elseif(!empty($sec['secret'])){
                    $wxuser = m('member')->wxuser($sec['appid'],$sec['secret']);
                    $params['openid'] = trim($wxuser['openid']);
                }

                $wechat = m('common')->wechat_native_build($params, $options, 0);
                if (!is_error($wechat)) {
                    $wechat['success'] = true;
                    if (!empty($params['openid'])){
                        $wechat['weixin'] = true;
                    }else{
                        $wechat['weixin_jie'] = true;
                    }
                }
            }
            $wechat['jie'] = $jie;
        }

        $alipay = array('success' => false);
        if(empty($seckill_goods) && empty($ispeerpay)){
            //非代付
            //非秒杀才能 支付宝，货到付款
            //支付宝
            if (isset($set['pay']) && $set['pay']['alipay'] == 1) {
                //如果开启支付宝
                if (is_array($setting['payment']['alipay']) && ($setting['payment']['alipay']['switch'] || $setting['payment']['alipay']['pay_switch'])) {

                    $params = array();
                    $params['tid'] = trim($log['tid']);
                    $params['user'] = trim($_W['openid']);
                    $params['fee'] = $order['price'];
                    $params['title'] = trim($param_title);

                    load()->func('communication');
                    load()->model('payment');
                    $setting = uni_setting($_W['uniacid'], array('payment'));
                    if (is_array($setting['payment'])) {
                        $options = $setting['payment']['alipay'];
                        $alipay = m('common')->alipay_build($params, $options, 0, $_W['openid']);
                        //file_put_contents(__DIR__."/a1.json", json_encode($alipay).PHP_EOL,8);
                        if (!empty($alipay['url'])) {
                            $alipay['url'] = urlencode($alipay['url']);
                            $alipay['success'] = true;
                        }
                    }
                }
            }

            list(,$payment) = m('common')->public_build();

            if ($payment['type'] == '4')
            {
                $params = array(
                    'service' => 'pay.alipay.native',
                    'body' => trim($param_title),
                    'out_trade_no' => trim($log['tid']),
                    'total_fee' => $order['price']
                );

                if (!empty($order['ordersn2'])) {
                    $params['out_trade_no'] = $log['tid'] . '_B';
                } else {
                    $params['out_trade_no'] = $log['tid'] . '_borrow';
                }

                $AliPay = m('pay')->build($params, $payment,0);
                if (!empty($AliPay) && !is_error($AliPay)){
                    $alipay['url'] = urlencode($AliPay['code_url']);
                    $alipay['success'] = true;
                }
            }

            //货到付款
            if (!empty($order['addressid']))
            {
                $cash = array('success' => $order['cash'] == 1 && isset($set['pay']) && $set['pay']['cash'] == 1 && $order['isverify'] == 0 && $order['isvirtual'] == 0);
            }

            $haveverifygood = m('order')->checkhaveverifygoods($orderid);


        } else{
            $peerPaySwi = false;//关掉代付
            $cash = array('success' => false);
        }


        $payinfo = array(
            'orderid' => $orderid,
            'ordersn' => trim($log['tid']),
            'credit' => $credit,
            'alipay' => $alipay,
            'wechat' => $wechat,
            'cash' => $cash,
            'money' => $order['price']
        );

        if (is_h5app()) {
            $payinfo = array(
                'wechat' => !empty($sec['app_wechat']['merchname']) && !empty($set['pay']['app_wechat']) && !empty($sec['app_wechat']['appid']) && !empty($sec['app_wechat']['appsecret']) && !empty($sec['app_wechat']['merchid']) && !empty($sec['app_wechat']['apikey']) && $order['price'] > 0 ? true : false,
                'alipay' => false,
                'mcname' => trim($sec['app_wechat']['merchname']),
                'aliname' => empty($_W['shopset']['shop']['name']) ? trim($sec['app_wechat']['merchname']) : trim($_W['shopset']['shop']['name']),
                'ordersn' => trim($log['tid']),
                'money' => $order['price'],
                'attach' => $_W['uniacid'] . ":0",
                'type' => 0,
                'orderid' => $orderid,
                'credit' => $credit,
                'cash' => $cash
            );
            if (!empty($order['ordersn2'])) {
                $var = sprintf("%02d", trim($order['ordersn2']));
                $payinfo['ordersn'] .= "GJ" . $var;
            }

            if( !empty($set['pay']['app_alipay']) && ( !empty($sec['app_alipay']['public_key'])|| !empty($sec['app_alipay']['public_key_rsa2']) ) ){
                $payinfo['alipay']=true;
            }
        }

        if (p('seckill') ) {

            foreach ($seckill_goods as $data) {
                plugin_run("seckill::getSeckill", $data['goodsid'], $data['optionid'], true, trim($_W['openid']));
            }
        }
        include $this->template();


    }

    function orderstatus()
    {
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $orderid = intval($_GPC['id']);
        $order = pdo_fetch("select status from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid));
        if ($order['status'] >= 1) {
            @session_start();
            $_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"] = 1;
            show_json(1);
        }
        show_json(0);
    }

    function complete()
    {

        global $_W, $_GPC;
        $orderid = intval($_GPC['id']);
        $uniacid = $_W['uniacid'];
        $openid = $_W['openid'];
        $gpc_ordersn = empty($_GPC['ordersn']) ? $_GPC['ordersn'] : str_replace(array('_borrow','_B'),'',$_GPC['ordersn']);
        $ispeerpay = m('order')->checkpeerpay($orderid);//检查是否是代付订单
        if (!empty($ispeerpay)) {//代付订单
            $_SESSION['peerpay'] =  $ispeerpay['id'];
            $peerpay = $_GPC['peerpay'];
            $peerpay = floatval(str_replace(',','',$peerpay));
            if ($ispeerpay['peerpay_type'] == 0 && $ispeerpay['peerpay_realprice'] != $peerpay){
                if ($_W['ispost']) {
                    show_json(0, '参数错误');
                } else {
                    $this->message('参数错误', mobileUrl('order'));
                }
            }elseif ($ispeerpay['peerpay_type'] == 1 && !empty($ispeerpay['peerpay_selfpay']) && $ispeerpay['peerpay_selfpay'] < $peerpay && floatval($ispeerpay['peerpay_selfpay']) > 0){
                if ($_W['ispost']) {
                    show_json(0, '参数错误');
                } else {
                    $this->message('参数错误', mobileUrl('order'));
                }
            }
            if ($peerpay<=0){
                if ($_W['ispost']) {
                    show_json(0, '参数错误');
                } else {
                    $this->message('参数错误', mobileUrl('order'));
                }
            }
            $openid = pdo_fetchcolumn("select openid from " . tablename('ewei_shop_order') . ' where id=:orderid and uniacid=:uniacid limit 1', array(':orderid' => $orderid, ':uniacid' => $uniacid));
            $peerpay_info = (float)pdo_fetchcolumn("select SUM(price) price from " . tablename('ewei_shop_order_peerpay_payinfo') . ' where pid=:pid limit 1'
                , array(':pid' => $ispeerpay['id']));
        }else{
            $_SESSION['peerpay'] =  null;
        }

        if (is_h5app() && empty($orderid)) {
            if (strexists($gpc_ordersn, 'GJ')) {
                $ordersns = explode("GJ", $gpc_ordersn);
                $ordersn = $ordersns[0];
            }else{
                $ordersn = $gpc_ordersn;
            }
            $ordersn = rtrim($ordersn, 'TR');
            $orderid = pdo_fetchcolumn("select id from " . tablename('ewei_shop_order') . ' where ordersn=:ordersn and uniacid=:uniacid and openid=:openid limit 1', array(':ordersn' => $ordersn, ':uniacid' => $uniacid, ':openid' => $openid));
        }

        if (empty($orderid)) {
            if ($_W['ispost']) {
                show_json(0, '参数错误');
            } else {
                $this->message('参数错误', mobileUrl('order'));
            }
        }

        $set = m('common')->getSysset(array('shop', 'pay'));
        $set['pay']['weixin'] = !empty($set['pay']['weixin_sub']) ? 1 : $set['pay']['weixin'];
        $set['pay']['weixin_jie'] = !empty($set['pay']['weixin_jie_sub']) ? 1 : $set['pay']['weixin_jie'];
        $member = m('member')->getMember($openid, true);

        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));

//        if(!empty($gpc_ordersn))
//        {
//            $order['ordersn'] = $gpc_ordersn;
//        }

        $go_flag = 0;
        if (empty($order['istrade']) && $order['status']>=1) {
            $go_flag =1;
        }
        if (!empty($order['istrade'])) {
            if ($order['status'] > 1 || ($order['status'] ==1 && $order['tradestatus'] == 2)) {
                $go_flag =1;
            }
        }

        if ($go_flag == 1) {
            $pay_result = true;
            if ($_W['ispost']) {
                $_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"] = 1;
                show_json(1, array('result'=>$pay_result));
            } else {
                header("location:" . mobileUrl('order/pay/success', array('id' => $order['id'],'result'=>$pay_result)));
                exit;
            }
        }


        //套餐订单
        if ($order['ispackage'] > 0) {
            $package = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_package') . " WHERE uniacid = " . $uniacid . " and id = " . $order['packageid'] . " ");
            if (empty($package)) {
                show_json(0, '未找到套餐！');
            }
            if ($package['starttime'] > time()) {
                show_json(0, '套餐活动未开始，请耐心等待！');
            }
            if ($package['endtime'] < time()) {
                show_json(0, '套餐活动已结束，谢谢您的关注，请您浏览其他套餐或商品！');
            }
        }


        if (empty($order)) {
            if ($_W['ispost']) {
                show_json(0, '订单未找到');
            } else {
                $this->message('订单未找到', mobileUrl('order'));
            }
        }

        $type = $_GPC['type'];

        if (!in_array($type, array('wechat', 'alipay', 'credit', 'cash'))) {
            if ($_W['ispost']) {
                show_json(0, '未找到支付方式');
            } else {
                $this->message('未找到支付方式', mobileUrl('order'));
            }
        }

        $log = pdo_fetch('SELECT * FROM ' . tablename('core_paylog') . ' WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid limit 1', array(':uniacid' => $uniacid, ':module' => 'ewei_shopv2', ':tid' => $order['ordersn']));
        if (empty($log) && empty($ispeerpay)) {
            if ($_W['ispost']) {
                show_json(0, '支付出错,请重试!');
            } else {
                $this->message('支付出错,请重试!', mobileUrl('order'));
            }
        }

        //判断是否为o2o支付
        if((empty($order['isnewstore']) || empty($order['storeid']))&&empty($order['istrade']))
        {
            $order_goods = pdo_fetchall('select og.id,g.title, og.goodsid,og.optionid,g.total as stock,og.total as buycount,g.status,g.deleted,g.maxbuy,g.usermaxbuy,g.istime,g.timestart,g.timeend,g.buylevels,g.buygroups,g.totalcnf,og.seckill from  ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id '
                . ' where og.orderid=:orderid and og.uniacid=:uniacid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $orderid));


            foreach ($order_goods as $data) {
                if (empty($data['status']) || !empty($data['deleted'])) {

                    if ($_W['ispost']) {
                        show_json(0, $data['title'] . '<br/> 已下架!');
                    } else {
                        $this->message($data['title'] . '<br/> 已下架!', mobileUrl('order'));
                    }
                }

                $unit = empty($data['unit']) ? '件' : $data['unit'];


                $seckillinfo = plugin_run("seckill::getSeckill", $data['goodsid'], $data['optionid'], true, $_W['openid']);

                if ($data['seckill']) {
                    //是秒杀的商品
                    if (empty($seckillinfo) || $seckillinfo['status'] != 0 || time() > $seckillinfo['endtime']) {
                        if ($_W['ispost']) {
                            show_json(0, $data['title'] . '<br/> 秒杀已结束，无法支付!');
                        } else {
                            $this->message($data['title'] . '<br/> 秒杀已结束，无法支付!', mobileUrl('order'));
                        }
                    }
                }

                if ($seckillinfo && $seckillinfo['status'] == 0 || !empty($ispeerpay)) {
                    //如果是秒杀，不判断任何条件//代付订单也不判断

                }else {

                    //最低购买
                    if ($data['minbuy'] > 0) {
                        if ($data['buycount'] < $data['minbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            } else {
                                $this->message($data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            }
                        }
                    }

                    //一次购买
                    if ($data['maxbuy'] > 0) {
                        if ($data['buycount'] > $data['maxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!");
                            } else {
                                $this->message($data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!", mobileUrl('order'));
                            }
                        }
                    }
                    //总购买量
                    if ($data['usermaxbuy'] > 0) {
                        $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('ewei_shop_order_goods') . ' og '
                            . ' left join ' . tablename('ewei_shop_order') . ' o on og.orderid=o.id '
                            . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                        if ($order_goodscount >= $data['usermaxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit);
                            } else {
                                $this->message($data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit, mobileUrl('order'));
                            }
                        }
                    }

                    //判断限时购
                    if ($data['istime'] == 1) {
                        if (time() < $data['timestart']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 限购时间未到!');
                            } else {
                                $this->message($data['title'] . '<br/> 限购时间未到!', mobileUrl('order'));
                            }
                        }
                        if (time() > $data['timeend']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 限购时间已过!');
                            } else {
                                $this->message($data['title'] . '<br/> 限购时间已过!', mobileUrl('order'));
                            }
                        }
                    }
                    //判断会员权限
                    if ($data['buylevels'] != '') {
                        $buylevels = explode(',', $data['buylevels']);
                        if (!in_array($member['level'], $buylevels)) {
                            if ($_W['ispost']) {
                                show_json(0, '您的会员等级无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您的会员等级无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }

                    if ($member['groupid'] == ''){
                        $groupid = array();
                    }else{
                        $groupid = explode(',',$member['groupid']);
                    }
                    //会员组权限
                    if ($data['buygroups'] != '') {
                        if(empty($groupid)){
                            $groupid[]=0;
                        }
                        $buygroups = explode(',', $data['buygroups']);
                        $groups_id = explode(',',$member['groupid']);
                        if (!array_intersect($groups_id, $buygroups)) {
                            if ($_W['ispost']) {
                                show_json(0, '您所在会员组无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您所在会员组无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }
                }
                if ($data['totalcnf'] == 1) {
                    if (!empty($data['optionid'])) {
                        $option = pdo_fetch('select id,title,marketprice,goodssn,productsn,stock,`virtual` from ' . tablename('ewei_shop_goods_option') . ' where id=:id and goodsid=:goodsid and uniacid=:uniacid  limit 1', array(':uniacid' => $uniacid, ':goodsid' => $data['goodsid'], ':id' => $data['optionid']));
                        if (!empty($option)) {
                            if ($option['stock'] != -1) {
                                if (empty($option['stock'])) {
                                    if ($_W['ispost']) {
                                        show_json(0, $data['title'] . "<br/>" . $option['title'] . " 库存不足!");
                                    } else {
                                        $this->message($data['title'] . "<br/>" . $option['title'] . " 库存不足!", mobileUrl('order'));
                                    }
                                }
                            }
                        }
                    } else {
                        if ($data['stock'] != -1) {
                            if (empty($data['stock'])) {
                                if ($_W['ispost']) {
                                    show_json(0, $data['title'] . "<br/>库存不足!");
                                } else {
                                    $this->message($data['title'] . "<br/>库存不足!", mobileUrl('order'));
                                }
                            }
                        }
                    }
                }
            }
            if(!$seckillinfo) {
                $ret = m('order')->CheckoodsStock($orderid, 1);
                if(!$ret) {
                    show_json(0, '您当前下单商品库存不足,请重新下单');
                }
            }
        }else{

            if(p('newstore')){
                $sql = "select og.id,g.title, og.goodsid,og.optionid,ng.stotal as stock,og.total as buycount,ng.gstatus as status,ng.deleted,g.maxbuy,g.usermaxbuy,g.istime,g.timestart,g.timeend,g.buylevels,g.buygroups,g.totalcnf,og.seckill,og.storeid "
                    . " from " . tablename('ewei_shop_order_goods')
                    . " og left join  " . tablename('ewei_shop_goods') . " g  on g.id=og.goodsid and g.uniacid=og.uniacid"
                    . " inner join " . tablename('ewei_shop_newstore_goods') . " ng on g.id=ng.goodsid   "
                    . " where og.orderid=:orderid and og.uniacid=:uniacid and ng.storeid=:storeid";
                $order_goods = pdo_fetchall($sql, array(':uniacid' => $uniacid, ':orderid' => $orderid,':storeid'=>$order['storeid']));
                
                foreach ($order_goods as $data) {
                    if (empty($data['status']) || !empty($data['deleted'])) {

                        if ($_W['ispost']) {
                            show_json(0, $data['title'] . '<br/> 已下架!');
                        } else {
                            $this->message($data['title'] . '<br/> 已下架!', mobileUrl('order'));
                        }
                    }

                    $unit = empty($data['unit']) ? '件' : $data['unit'];


                    //最低购买
                    if ($data['minbuy'] > 0) {
                        if ($data['buycount'] < $data['minbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            } else {
                                $this->message($data['title'] . '<br/> ' . $data['min'] . $unit . "起售!", mobileUrl('order'));
                            }
                        }
                    }

                    //一次购买
                    if ($data['maxbuy'] > 0) {
                        if ($data['buycount'] > $data['maxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!");
                            } else {
                                $this->message($data['title'] . '<br/> 一次限购 ' . $data['maxbuy'] . $unit . "!", mobileUrl('order'));
                            }
                        }
                    }
                    //总购买量
                    if ($data['usermaxbuy'] > 0) {
                        $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('ewei_shop_order_goods') . ' og '
                            . ' left join ' . tablename('ewei_shop_order') . ' o on og.orderid=o.id '
                            . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $data['goodsid'], ':uniacid' => $uniacid, ':openid' => $openid));
                        if ($order_goodscount >= $data['usermaxbuy']) {
                            if ($_W['ispost']) {
                                show_json(0, $data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit);
                            } else {
                                $this->message($data['title'] . '<br/> 最多限购 ' . $data['usermaxbuy'] . $unit, mobileUrl('order'));
                            }
                        }
                    }


                    //判断会员权限
                    if ($data['buylevels'] != '') {
                        $buylevels = explode(',', $data['buylevels']);
                        if (!in_array($member['level'], $buylevels)) {
                            if ($_W['ispost']) {
                                show_json(0, '您的会员等级无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您的会员等级无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }

                    if ($member['groupid'] == ''){
                        $groupid = array();
                    }else{
                        $groupid = explode(',',$member['groupid']);
                    }
                    //会员组权限
                    if ($data['buygroups'] != ''){
                        if(empty($groupid)){
                            $groupid[]=0;
                        }
                        $buygroups = explode(',', $data['buygroups']);
                        $intersect = array_intersect($groupid, $buygroups);
                        if (empty($intersect)) {
                            if ($_W['ispost']) {
                                show_json(0, '您所在会员组无法购买<br/>' . $data['title'] . '!');
                            } else {
                                $this->message('您所在会员组无法购买<br/>' . $data['title'] . '!', mobileUrl('order'));
                            }
                        }
                    }
                    if ($data['totalcnf'] == 1) {
                        if (!empty($data['optionid'])) {
                            $option = pdo_fetch('select id,marketprice,stock from ' . tablename('ewei_shop_newstore_goods_option') . ' where optionid=:optionid and goodsid=:goodsid and uniacid=:uniacid and storeid=:storeid  limit 1', array(':uniacid' => $uniacid, ':goodsid' => $data['goodsid'], ':optionid' => $data['optionid'],':storeid'=>$data['storeid']));
                            if (!empty($option)) {
                                if ($option['stock'] != -1) {
                                    if (empty($option['stock'])) {
                                        if ($_W['ispost']) {
                                            show_json(0, $data['title'] . "<br/>" . $option['title'] . " 库存不足!");
                                        } else {
                                            $this->message($data['title'] . "<br/>" . $option['title'] . " 库存不足!", mobileUrl('order'));
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($data['stock'] != -1) {
                                if (empty($data['stock'])) {
                                    if ($_W['ispost']) {
                                        show_json(0, $data['title'] . "<br/>库存不足!");
                                    } else {
                                        $this->message($data['title'] . "<br/>库存不足!", mobileUrl('order'));
                                    }
                                }
                            }
                        }
                    }
                }
            }else{
                if ($_W['ispost']) {
                    show_json(0, '门店歇业,不能付款!');
                } else {
                    $this->message("门店歇业,不能付款!", mobileUrl('order'));
                }
            }

        }

        //货到付款//代付不判断货到付款
        if ($type == 'cash' && empty($ispeerpay)) {

            //判断是否开启货到付款
            if (empty($set['pay']['cash'])) {
                if ($_W['ispost']) {
                    show_json(0, '未开启货到付款!');
                } else {
                    $this->message("未开启货到付款", mobileUrl('order'));
                }
            }

            m('order')->setOrderPayType($order['id'], 3, $gpc_ordersn);

            $ret = array();
            $ret['result'] = 'success';
            $ret['type'] = 'cash';
            $ret['from'] = 'return';
            $ret['tid'] = $log['tid'];
            $ret['user'] = $order['openid'];
            $ret['fee'] = $order['price'];
            $ret['weid'] = $_W['uniacid'];
            $ret['uniacid'] = $_W['uniacid'];
            $pay_result = m('order')->payResult($ret);
            pdo_update('ewei_shop_order',array('cashtime'=>time()),array('id'=>$order['id']));
            //模板消息
            m('notice')->sendOrderMessage($order['id']);

            @session_start();
            $_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"] = 1;
            if ($_W['ispost']) {
                show_json(1, array('result'=>$pay_result));
            } else {
                header("location:" . mobileUrl('order/pay/success', array('id' => $order['id'],'result'=>$pay_result,)));
            }
        }

        if (!empty($ispeerpay)){

            $total = $peerpay_info+$peerpay;
            if ($total>=$ispeerpay['peerpay_realprice']){
                unset($_SESSION['peerpay']);
            }
            if ($total>$ispeerpay['peerpay_realprice']){
                if ($_W['ispost']){
                    show_json(0,'不能超付');
                }else{
                    $this->message('不能超付');
                }
            }
            $log['fee'] = $peerpay;
            $openid = $_W['openid'];
            $member = m('member')->getMember($openid,true);
        }
        $ps = array();
        $ps['tid'] = $log['tid'];
        $ps['user'] = $openid;
        $ps['fee'] = $log['fee'];
        $ps['title'] = $log['title'];


        //余额支付
        if ($type == 'credit') {
            //高并发下单支付库款多次的问题
            $open_redis = function_exists('redis') && !is_error(redis());
            if( $open_redis ) {
                $redis_key = "{$_W['uniacid']}_member_order__pay_{$orderid}";
                $redis = redis();
                if (!is_error($redis)) {
                    if($redis->get($redis_key)) {
                        show_json(0,'请勿重复点击');
                    }
                    $redis->setex($redis_key, 1,time());
                }
            }
            //判断是否开启余额支付
            if (empty($set['pay']['credit']) && $ps['fee'] > 0) {
                if ($_W['ispost']) {
                    show_json(0, '未开启余额支付!');
                } else {
                    $this->message("未开启余额支付", mobileUrl('order'));
                }
            }

            if ($ps['fee'] < 0) {
                if ($_W['ispost']) {
                    show_json(0, "金额错误");
                } else {
                    $this->message("金额错误", mobileUrl('order'));
                }
            }

            $credits = m('member')->getCredit($openid, 'credit2');
            if ($credits < $ps['fee']) {
                if ($_W['ispost']) {
                    show_json(0, "余额不足,请充值");
                } else {
                    $this->message("余额不足,请充值", mobileUrl('order'));
                }
            }
            $fee = floatval($ps['fee']);

            $result = m('member')->setCredit($openid, 'credit2', -$fee, array($_W['member']['uid'], $_W['shopset']['shop']['name'] . '消费' . $fee));
            if (is_error($result)) {
                if ($_W['ispost']) {
                    show_json(0, $result['message']);
                } else {
                    $this->message($result['message'], mobileUrl('order'));
                }
            }
            $record = array();
            $record['status'] = '1';
            $record['type'] = 'cash';
            pdo_update('core_paylog', $record, array('plid' => $log['plid']));

            m('order')->setOrderPayType($order['id'], 1, $gpc_ordersn);

            $ret = array();
            $ret['result'] = 'success';
            $ret['type'] = $log['type'];
            $ret['from'] = 'return';
            $ret['tid'] = $log['tid'];
            $ret['user'] = $log['openid'];
            $ret['fee'] = $log['fee'];
            $ret['weid'] = $log['weid'];
            $ret['uniacid'] = $log['uniacid'];
            @session_start();
            $_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"] = 1;
            if (!empty($ispeerpay)){
                $peerheadimg = m('member')->getInfo($member['openid']);
                if (empty($peerheadimg['avatar'])){
                    $peerheadimg['avatar'] = 'http://of6odhdq1.bkt.clouddn.com/d7fd47dc6163ec00abfe644ab3c33ac6.jpg';
                }
               m('order')->peerStatus(array('pid'=>$ispeerpay['id'],'uid'=>$member['id'],'uname'=>$member['nickname'],'usay'=>'','price'=>$log['fee'],'createtime'=>time(),'headimg'=>$peerheadimg['avatar'],'openid'=>$peerheadimg['openid'],'usay'=>trim($_GPC['peerpaymessage'])));
            }

            $pay_result = m('order')->payResult($ret);

            //高并发代付时支付完成清除
            $open_redis = function_exists('redis') && !is_error(redis());
            if( $open_redis ) {
                $redis_key = "{$_W['uniacid']}_peerpay_order__pay_{$ispeerpay['id']}";
                $redis = redis();
                $redis->delete($redis_key);
            }

            if ($_W['ispost']) {
                show_json(1, array('result'=>$pay_result));
            } else {
                header("location:" . mobileUrl('order/pay/success', array('id' => $order['id'],'result'=>$pay_result)));
            }
        } else if ($type == 'wechat') {

            //判断是否开启微信支付
            if (!is_weixin() && empty($_W['shopset']['wap']['open'])) {
                if ($_W['ispost']) {
                    show_json(0, is_h5app() ? "APP正在维护" : '非微信环境!');
                } else {
                    $this->message(is_h5app() ? "APP正在维护" : '非微信环境!', mobileUrl('order'));
                }
            }
            if (((empty($set['pay']['weixin']) && empty($set['pay']['weixin_jie'])) && is_weixin()) || (empty($set['pay']['app_wechat']) && is_h5app())) {
                if ($_W['ispost']) {
                    show_json(0, '未开启微信支付!');
                } else {
                    $this->message('未开启微信支付!', mobileUrl('order'));
                }
            }

            $ordersn = $order['ordersn'];

            if (!empty($order['ordersn2'])) {
                $ordersn .= "GJ" . sprintf("%02d", $order['ordersn2']);
            }
            if (!empty($ispeerpay)){
                $payquery = m('finance')->isWeixinPay($_SESSION['peerpaytid'], $order['price'], is_h5app() ? true : false);
                $payquery_jie = m('finance')->isWeixinPayBorrow($_SESSION['peerpaytid'], $order['price']);

            }else{
                $payquery = m('finance')->isWeixinPay($ordersn, $order['price'], is_h5app() ? true : false);
                $payquery_jie = m('finance')->isWeixinPayBorrow($ordersn, $order['price']);
            }


//            if (!empty($ispeerpay) && $_SESSION['peerpaytid']){
//                m('order')->setOrderPayType($order['id'], 21);
//                m('order')->peerStatus(array('pid'=>$ispeerpay['id'],'uid'=>$member['id'],'uname'=>$member['nickname'],'usay'=>trim($_GPC['peerpaymessage']),'price'=>$log['fee'],'createtime'=>time(),'openid'=>$member['openid'],'headimg'=>$member['avatar'],'tid'=>$_SESSION['peerpaytid']));
//                unset($_SESSION['peerpaytid']);
//                $peerpay_info = (float)pdo_fetchcolumn("select SUM(price) from " . tablename('ewei_shop_order_peerpay_payinfo') . ' where pid=:pid limit 1', array(':pid' => $ispeerpay['id']));
//                if ($ispeerpay['peerpay_realprice'] <= $peerpay_info){
//                    $ret = array();
//                    $ret['result'] = 'success';
//                    $ret['type'] = 'wechat';
//                    $ret['from'] = 'return';
//                    $ret['tid'] = $log['tid'];
//                    $ret['user'] = $log['openid'];
//                    $ret['fee'] = $log['fee'];
//                    $ret['weid'] = $log['weid'];
//                    $ret['uniacid'] = $log['uniacid'];
//                    $ret['deduct'] = intval($_GPC['deduct']) == 1;
//                    $pay_result = m('order')->payResult($ret);
//                    @session_start();
//                    $_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"] = 1;
//                    if ($_W['ispost']) {
//                        show_json(1, array('result'=>$pay_result));
//                    } else {
//                        header("location:" . mobileUrl('order/pay/success', array('id' => $order['id'],'result'=>$pay_result)));
//                    }
//                    exit;
//                }
//                show_json(1,'支付成功');
//
//            }
            if (!is_error($payquery) || !is_error($payquery_jie) || !empty($ispeerpay)) {
                //微信支付
                $record = array();
                $record['status'] = '1';
                $record['type'] = 'wechat';
                pdo_update('core_paylog', $record, array('plid' => $log['plid']));

                m('order')->setOrderPayType($order['id'], 21, $gpc_ordersn);
                if (is_h5app()) {
                    pdo_update('ewei_shop_order', array('apppay' => 1), array('id' => $order['id']));
                }

                $ret = array();
                $ret['result'] = 'success';
                $ret['type'] = 'wechat';
                $ret['from'] = 'return';
                $ret['tid'] = $log['tid'];
                $ret['user'] = $log['openid'];
                $ret['fee'] = $log['fee'];
                $ret['weid'] = $log['weid'];
                $ret['uniacid'] = $log['uniacid'];
                $ret['deduct'] = intval($_GPC['deduct']) == 1;
                if (!empty($ispeerpay)){
                    //添加自定义代付信息
                    $udata = array();
                    $udata['usay'] = trim($_GPC['peerpaymessage']);
                    pdo_update('ewei_shop_order_peerpay_payinfo', $udata, array('pid'=>$ispeerpay['id'],'openid'=>$_W['openid']));
                }
                $pay_result = m('order')->payResult($ret);
                @session_start();
                $_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"] = 1;

                if ($_W['ispost']) {
                    show_json(1, array('result'=>$pay_result));
                } else {
                    header("location:" . mobileUrl('order/pay/success', array('id' => $order['id'],'result'=>$pay_result)));
                }
                exit;
            }
            if ($_W['ispost']) {
                show_json(0, '支付出错,请重试!');
            } else {
                $this->message('支付出错,请重试!', mobileUrl('order'));
            }
        }
    }

    /*
    function alipay_complete() {
        global $_GPC, $_W;

        $set = m('common')->getSysset(array('shop', 'pay'));

        //判断是否开启支付宝支付

        $tid = $_GPC['out_trade_no'];

        if(is_h5app()){
            $sec = m('common')->getSec();
            $sec =iunserializer($sec['sec']);
            $public_key = $sec['app_alipay']['public_key'];
            
            if(empty($set['pay']['app_alipay']) || empty($public_key)){
                $this->message('支付出现错误，请重试(1)!', mobileUrl('order'));
            }

            $alidata = base64_decode($_GET['alidata']);
            $alidata = json_decode($alidata, true);
            $alisign = m('finance')->RSAVerify($alidata, $public_key, false);

            $tid = $this->str($alidata['out_trade_no']);
            
            if($alisign==0){
                $this->message('支付出现错误，请重试(2)!', mobileUrl('order'));
            }

        }else{

            if(empty($set['pay']['alipay']) && is_weixin()){
                $this->message('未开启支付宝支付!', mobileUrl('order'));
            }
            if (!m('finance')->isAlipayNotify($_GET)) {
                $this->message('支付出现错误，请重试!', mobileUrl('order'));
            }

        }


        $log = pdo_fetch('SELECT * FROM ' . tablename('core_paylog') . ' WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid limit 1', array(':uniacid' => $_W['uniacid'], ':module' => 'ewei_shopv2', ':tid' => $tid));

        if (empty($log)) {
            $this->message('支付出现错误，请重试(3)!', mobileUrl('order'));
        }

        if(is_h5app()){
            $alidatafee = $this->str($alidata['total_fee']);
            $alidatastatus = $this->str($alidata['success']);
            if($log['fee']!=$alidatafee || !$alidatastatus){
                $this->message('支付出现错误，请重试(4)!', mobileUrl('order'));
            }
        }

        if ($log['status'] != 1) {
            //支付宝支付
            $record = array();
            $record['status'] = '1';
            $record['type'] = 'alipay';
            pdo_update('core_paylog', $record, array('plid' => $log['plid']));

            $ret = array();
            $ret['result'] = 'success';
            $ret['type'] = 'alipay';
            $ret['from'] = 'return';
            $ret['tid'] = $log['tid'];
            $ret['user'] = $log['openid'];
            $ret['fee'] = $log['fee'];
            $ret['weid'] = $log['weid'];
            $ret['uniacid'] = $log['uniacid'];
            m('order')->payResult($ret);
        }
        //取orderid
        $orderid = pdo_fetchcolumn('select id from ' . tablename('ewei_shop_order') . ' where ordersn=:ordersn and uniacid=:uniacid', array(':ordersn' => $log['tid'], ':uniacid' => $_W['uniacid']));

        if (!empty($orderid))  {
            m('order')->setOrderPayType($orderid, 22);
            if(is_h5app()){
                pdo_update('ewei_shop_order', array('apppay' => 1), array('id' => $orderid ));
            }
        }

        $url = mobileUrl('order/detail', array('id' => $orderid),true);
        die("<script>top.window.location.href='{$url}'</script>");
    }*/

    function success()
    {
        global $_W, $_GPC;
        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $member = m('member')->getMember($openid, true);
        $orderid = intval($_GPC['id']);

        if (empty($orderid)) {
            $this->message('参数错误', mobileUrl('order'), 'error');
        }
        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));

        @session_start();
        if (empty($_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"])) {
            if (empty($order['istrade'])) {
                header('location: ' . mobileUrl('order'));
            } else {
                header('location: ' . mobileUrl('newstore/norder'));
            }
            exit;
        }
        unset($_SESSION[EWEI_SHOPV2_PREFIX . "_order_pay_complete"]);

        $hasverifygood  = m("order")->checkhaveverifygoods($orderid);
        $isonlyverifygoods  = m("order")->checkisonlyverifygoods($orderid);

        $ispeerpay = m('order')->checkpeerpay($orderid);
        if (!empty($ispeerpay)) {//代付
            $peerpay = floatval($_GPC['peerpay']);
            $openid = pdo_fetchcolumn("select openid from " . tablename('ewei_shop_order') . ' where id=:orderid and uniacid=:uniacid limit 1', array(':orderid' => $orderid, ':uniacid' => $uniacid));
            $order['price'] = $ispeerpay['realprice'];
            $peerpayuid = m('member')->getInfo($_W['openid']);
            $peerprice = pdo_fetch("SELECT `price` FROM ".tablename('ewei_shop_order_peerpay_payinfo')." WHERE uid = :uid ORDER BY id DESC LIMIT 1",array(':uid'=>$peerpayuid['id']));

            //查询是否存在支付领优惠券活动
            $share = false;
            if(com('coupon')){
                $share  = com('coupon') -> activity(empty($peerprice)?0:$peerprice['price']);
            }
        }else{

            if (!empty($order['istrade'])) {
                if($order['status'] == 1 && $order['tradestatus'] == 1) {
                    $order['price'] = $order['dowpayment'];
                } else if ($order['status'] == 1 && $order['tradestatus'] == 2){
                    $order['price'] = $order['betweenprice'];
                }
            }

            $merchid = $order['merchid'];
            //商品
            $goods = pdo_fetchall("select og.goodsid,og.price,g.title,g.thumb,og.total,g.credit,og.optionid,og.optionname as optiontitle,g.isverify,g.storeids from " . tablename('ewei_shop_order_goods') . " og "
                . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
                . " where og.orderid=:orderid and og.uniacid=:uniacid ", array(':uniacid' => $uniacid, ':orderid' => $orderid));

            //地址
            $address = false;
            if (!empty($order['addressid'])) {
                $address = iunserializer($order['address']);
                if (!is_array($address)) {
                    $address = pdo_fetch('select * from  ' . tablename('ewei_shop_member_address') . ' where id=:id limit 1', array(':id' => $order['addressid']));
                }
            }

            //联系人
            $carrier = @iunserializer($order['carrier']);
            if (!is_array($carrier) || empty($carrier)) {
                $carrier = false;
            }

            //自提点
            $store = false;
            if (!empty($order['storeid'])) {
                if ($merchid > 0) {
                    $store = pdo_fetch('select * from  ' . tablename('ewei_shop_merch_store') . ' where id=:id limit 1', array(':id' => $order['storeid']));
                } else {
                    $store = pdo_fetch('select * from  ' . tablename('ewei_shop_store') . ' where id=:id limit 1', array(':id' => $order['storeid']));
                }
            }

            //核销门店
            $stores = false;
            if ($order['isverify']) {
                //核销单
                $storeids = array();
                foreach ($goods as $g) {
                    if (!empty($g['storeids'])) {
                        $storeids = array_merge(explode(',', $g['storeids']), $storeids);
                    }
                }


                if (p('newstore')&& !empty($order['isnewstore'])){
                    $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id = :id  and uniacid=:uniacid and status=1  order by displayorder desc,id desc', array(':uniacid' => $_W['uniacid'],':id'=>$order['storeid']));

                }else  if (empty($storeids)) {
                    //全部门店
                    if ($merchid > 0) {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where  uniacid=:uniacid and merchid=:merchid and status=1 and type in (2,3) order by displayorder desc,id desc', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                    } else {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where  uniacid=:uniacid and status=1 and `type` in (2,3)  order by displayorder desc,id desc', array(':uniacid' => $_W['uniacid']));
                    }
                } else {
                    if ($merchid > 0) {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and merchid=:merchid and status=1 order by displayorder desc,id desc', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                    } else {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and status=1 order by displayorder desc,id desc', array(':uniacid' => $_W['uniacid']));
                    }
                }
            }

            //抽奖模块
            if(p('lottery')){
                $lottery_changes = p('lottery')->check_isreward();
            }

            //查询是否存在支付领优惠券活动
            $share = false;
            if(com('coupon')){
                $share = com('coupon') -> activity($order['price']);
            }
        }

        // 虚拟卡密
        if(!empty($order['virtual']) && !empty($order['virtual_str'])){
            $ordervirtual = m('order')->getOrderVirtual($order);
            $virtualtemp = pdo_fetch('SELECT linktext, linkurl FROM '. tablename('ewei_shop_virtual_type'). ' WHERE id=:id AND uniacid=:uniacid LIMIT 1', array(':id'=>$order['virtual'], ':uniacid'=>$_W['uniacid']));
        }
        $plugincoupon = com('coupon');
        if ($plugincoupon) {
            $plugincoupon->useConsumeCoupon($orderid);
        }

        //秒杀风格色
        if($order['seckilldiscountprice']>0 && p('diypage')){
            $diypagedata = m('common')->getPluginset('diypage');
            $diypage = p('diypage')->seckillPage($diypagedata['seckill']);
            if(!empty($diypage)){
                $seckill_color=$diypage['seckill_color'];
            }
        }

        include $this->template();

    }

    protected function str($str)
    {
        $str = str_replace('"', '', $str);
        $str = str_replace("'", '', $str);
        return $str;
    }

    function check()
    {

        global $_W, $_GPC;
        $orderid = intval($_GPC['id']);

        $og_array = m('order')->checkOrderGoods($orderid);
        if (!empty($og_array['flag'])) {
            show_json(0, $og_array['msg']);
        }
        show_json(1);
    }

    function message($msg, $redirect = '', $type = '')
    {
        global $_W;
        $title = "";
        $buttontext = "";
        $message = $msg;
        if (is_array($msg)) {
            $message = isset($msg['message']) ? $msg['message'] : '';
            $title = isset($msg['title']) ? $msg['title'] : '';
            $buttontext = isset($msg['buttontext']) ? $msg['buttontext'] : '';
        }
        if (empty($redirect)) {
            $redirect = 'javascript:history.back(-1);';
        } elseif ($redirect == 'close') {
            $redirect = 'javascript:WeixinJSBridge.call("closeWindow")';
        }
        include $this->template('_message');
        exit;
    }

    //代付确认页面
    public function peerpay()
    {
        global $_W,$_GPC;
        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $orderid = intval($_GPC['id']);

        $PeerPay = com_run('sale::getPeerPay');

        if (empty($orderid) || empty($PeerPay)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }

        $peerpay = (int)pdo_fetchcolumn("select orderid from " . tablename('ewei_shop_order_peerpay') . ' where orderid=:id and uniacid=:uniacid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid));

        if (!empty($peerpay)){
            header('location: ' . mobileUrl('order/pay/peerpayshare',array('id'=>$peerpay)));
            exit;
        }

        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid));

        if($order['ordersn2']>=100){
            $order['ordersn2'] = 0;
            pdo_update('ewei_shop_order', array('ordersn2' =>0), array('id' => $orderid, 'uniacid' => $uniacid));
        }
        if (!empty($order['ordersn2'])){
            $this->message('改价订单不支持代付');
        }

        if ($_W['ispost']){
            $data = array();
            $data['uniacid'] = $_W['uniacid'];
            $data['orderid'] = $orderid;
            $data['peerpay_type'] = (int)$_GPC['type'];
            $data['peerpay_price'] = (float)$order['price'];
            $data['peerpay_realprice'] = $order['price']>=$PeerPay['peerpay_price'] ?  round($order['price']-$PeerPay['peerpay_privilege'] ,2) : (float)$order['price'];
            $data['peerpay_selfpay'] = $PeerPay['self_peerpay'];
            $data['peerpay_message'] = trim($_GPC['message']);
            $data['status'] = 0;
            $data['createtime'] = time();
            $res = pdo_insert('ewei_shop_order_peerpay',$data);
            $insert_id = pdo_insertid();
            if ($res){
                show_json(1,array(
                    'url'=>mobileUrl('order/pay/peerpayshare',array('id'=>$orderid))
                ));
            }
            show_json(0);
        }


        if (empty($order)){
            header('location: ' . mobileUrl('order'));
            exit;
        }
        if($peerpay['isparent']==1){
            $scondition = " parentorderid=:id ";
        }else{
            $scondition = " orderid=:id";
        }
//        $ordergoods = pdo_fetch("select * from " . tablename('ewei_shop_order_goods') . ' where orderid=:id and uniacid=:uniacid limit 1'
//            , array(':id' => $orderid, ':uniacid' => $uniacid));
//        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1'
//            , array(':id' => $ordergoods['goodsid'], ':uniacid' => $uniacid));
        $goods =  pdo_fetchall("SELECT g.id,og.goodsid,og.total,g.title,g.thumb,g.type,g.status,og.price,og.title as gtitle,og.optionname as optiontitle,og.optionid,op.specs,g.merchid,og.seckill,og.seckill_taskid,
                og.sendtype,og.expresscom,og.expresssn,og.express,og.sendtime,og.finishtime,og.remarksend
                FROM " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on og.goodsid = g.id "
            . " left join " . tablename('ewei_shop_goods_option') . " op on og.optionid = op.id "
            . " where $scondition and og.uniacid=:uniacid order by og.id asc", array(':id' => $orderid, ':uniacid' => $uniacid));
        $address = iunserializer($order['address']);
        $member = m('member')->getMember($openid, true);
        $orderMember = m('member')->getMember($order['openid'], true);

        include $this->template();
    }

    //代付分享页面
    public function peerpayshare()
    {
        global $_W,$_GPC;
        $peerid = intval($_GPC['id']);
        $uniacid = $_W['uniacid'];
        $peerpay = pdo_fetch("select p.*,o.openid from " . tablename('ewei_shop_order_peerpay') . ' p join '.tablename('ewei_shop_order').' o on o.id=p.orderid where p.orderid=:id and p.uniacid=:uniacid limit 1'
            , array(':id' => $peerid, ':uniacid' => $uniacid));

        if (empty($peerpay)){
            header('location: ' . mobileUrl('order'));
            exit();
        }elseif ($peerpay['openid'] !== $_W['openid']){//不是本人
            header('location: ' . mobileUrl('order/pay/peerpaydetail',array('id'=>$peerid)));
            exit();
        }
        $peerpay_info = (float)pdo_fetchcolumn("select SUM(price) price from " . tablename('ewei_shop_order_peerpay_payinfo') . ' where pid=:pid limit 1'
            , array(':pid' => $peerpay['id']));
        $rate = round($peerpay_info / $peerpay['peerpay_realprice'] * 100,2);
        $rate_price = round($peerpay['peerpay_realprice'] - $peerpay_info,2);
        $member = m('member')->getMember($peerpay['openid'], true);

        $ordergoods = pdo_fetch("select * from " . tablename('ewei_shop_order_goods') . ' where orderid=:id and uniacid=:uniacid limit 1'
            , array(':id' => $peerpay['orderid'], ':uniacid' => $uniacid));
        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1'
            , array(':id' => $ordergoods['goodsid'], ':uniacid' => $uniacid));

        $_W['shopshare'] = array(
            'title' => '我想对你说：'.$peerpay['peerpay_message'],
            'imgUrl' => tomedia($goods['thumb']),
            'desc' => $peerpay['peerpay_message'],
            'link' => mobileUrl('order/pay/peerpaydetail',array('id'=>$peerid,'mid'=>$member['id']),1)
        );

        include $this->template();
    }

    public function peerpaydetail()
    {
        global $_W,$_GPC;
        $peerid = intval($_GPC['id']);
        $uniacid = $_W['uniacid'];
        $peerpay = pdo_fetch("select p.*,o.openid,o.address,o.isparent,o.id as oid from " . tablename('ewei_shop_order_peerpay') . ' p join '.tablename('ewei_shop_order').' o on o.id=p.orderid where p.orderid=:id and p.uniacid=:uniacid limit 1'
            , array(':id' => $peerid, ':uniacid' => $uniacid));
        if (empty($peerpay)){
            header('location: ' . mobileUrl('order'));
        }
        $PeerPay = com_run('sale::getPeerPay');

        $member = m('member')->getMember($peerpay['openid'], true);
        //已代付金额
        $peerpay_info = (float)pdo_fetchcolumn("select SUM(price) price from " . tablename('ewei_shop_order_peerpay_payinfo') . ' where pid=:pid limit 1', array(':pid' => $peerpay['id']));
        //剩余未付款金额
        $rate_price = round($peerpay['peerpay_realprice'] - $peerpay_info,2);
        //完成百分比
        $rate = round($peerpay_info / $peerpay['peerpay_realprice'] * 100,2);
        if($peerpay['isparent']==1){
            $scondition = " parentorderid=:id ";
        }else{
            $scondition = " orderid=:id";
        }
       // $ordergoods = pdo_fetchall("select * from " . tablename('ewei_shop_order_goods') . ' where '.$scondition.' and uniacid=:uniacid ', array(':id' => $peerpay['orderid'], ':uniacid' => $uniacid));

        $goods =  pdo_fetchall("SELECT g.id,og.goodsid,og.total,g.title,g.thumb,g.type,g.status,og.price,og.title as gtitle,og.optionname as optiontitle,og.optionid,op.specs,g.merchid,og.seckill,og.seckill_taskid,
                og.sendtype,og.expresscom,og.expresssn,og.express,og.sendtime,og.finishtime,og.remarksend
                FROM " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on og.goodsid = g.id "
            . " left join " . tablename('ewei_shop_goods_option') . " op on og.optionid = op.id "
            . " where $scondition and og.uniacid=:uniacid order by og.id asc", array(':id' => $peerpay['orderid'], ':uniacid' => $uniacid));






//        $goodslist=array();
//        foreach ($ordergoods as $key=>$goods){
//            $goods_info = pdo_fetch("select * from " . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1'
//                , array(':id' => $goods['goodsid'], ':uniacid' => $uniacid));
//            $goodslist[]=array(
//                'id'=>$goods_info['id'],
//                'title'=>$goods_info['title'],
//                'thumb'=>$goods_info['thumb'],
//            );
//     }





        $address = !empty($peerpay['address']) ? iunserializer($peerpay['address']) : '';

        if($peerpay['peerpay_type'] == 0){//单人代付
            $price = $peerpay['peerpay_realprice'];
        }else{//多人代付
            //代付留言列表
            $message = pdo_fetchall("SELECT * FROM ".tablename('ewei_shop_order_peerpay_payinfo')." WHERE pid = :pid ORDER BY id DESC LIMIT 3",array(':pid'=>$peerpay['id']));
            $price = $rate_price > $peerpay['peerpay_selfpay'] ? $peerpay['peerpay_selfpay'] : $rate_price;
        }
        $_W['shopshare'] = array(
            'title' => '我想对你说：'.$peerpay['peerpay_message'],
            'imgUrl' => tomedia($ordergoods['thumb']),
            'desc' => $peerpay['peerpay_message'],
            'link' => mobileUrl('order/pay/peerpaydetail',array('id'=>$peerid),1)
        );
        include $this->template();
    }



}
