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

class Order_EweiShopV2Model
{

    /*
     * 商品全返
     * */
    function fullback($orderid){
        global $_W;
        $uniacid = $_W['uniacid'];
        $order_goods = pdo_fetchall("select o.openid,og.optionid,og.goodsid,og.price,og.total from ".tablename("ewei_shop_order_goods")." as og
                    left join ".tablename('ewei_shop_order')." as o on og.orderid = o.id
                    where og.uniacid = ".$uniacid." and og.orderid = ".$orderid." ");
        /*$order = pdo_fetch('select o.id,o.ordersn,o.openid,og.optionid,og.goodsid,og.price from ' . tablename('ewei_shop_order') . ' as o
                left join '.tablename('ewei_shop_order_goods').' as og on og.orderid = o.id
                where  o.id=:id and o.uniacid=:uniacid limit 1', array(':uniacid' => $uniacid, ':id' => $orderid));*/
        foreach ($order_goods as $key => $value){
            if($value['optionid']>0){
                $goods = pdo_fetch("select g.hasoption,g.id,go.goodsid,go.isfullback from " . tablename('ewei_shop_goods') . ' as g
                left join '.tablename('ewei_shop_goods_option').' as go on go.goodsid = :id and go.id = '.$value['optionid'].'
                 where g.id=:id and g.uniacid=:uniacid limit 1'
                    , array(':id' => $value['goodsid'], ':uniacid' => $uniacid));
            }else{
                $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1'
                    , array(':id' => $value['goodsid'], ':uniacid' => $uniacid));
            }
            if($goods['isfullback']>0){
                $fullbackgoods = pdo_fetch("SELECT id,minallfullbackallprice,maxallfullbackallprice,minallfullbackallratio,maxallfullbackallratio,`day`,
                          fullbackprice,fullbackratio,status,hasoption,marketprice,`type`,startday
                          FROM ".tablename('ewei_shop_fullback_goods')." WHERE uniacid = ".$uniacid." and goodsid = ".$value['goodsid']." limit 1");
                //return $fullbackgoods;
                if(!empty($fullbackgoods) && $goods['hasoption'] && $value['optionid']>0){
                    $option = pdo_fetch('select id,title,allfullbackprice,allfullbackratio,fullbackprice,fullbackratio,`day` from ' . tablename('ewei_shop_goods_option') . ' 
                        where id=:id and goodsid=:goodsid and uniacid=:uniacid and isfullback = 1 limit 1',
                        array(':uniacid' => $uniacid, ':goodsid' => $value['goodsid'], ':id' => $value['optionid']));
                    if(!empty($option)){
                        $fullbackgoods['minallfullbackallprice'] = $option['allfullbackprice'];
                        $fullbackgoods['minallfullbackallratio'] = $option['allfullbackratio'];
                        $fullbackgoods['fullbackprice'] = $option['fullbackprice'];
                        $fullbackgoods['fullbackratio'] = $option['fullbackratio'];
                        $fullbackgoods['day'] = $option['day'];
                    }
                }
                //return $order;
                $fullbackgoods['startday'] = $fullbackgoods['startday'] -1 ;
                if(!empty($fullbackgoods)){
                    $data = array(
                        'uniacid' => $uniacid,
                        'orderid' => $orderid,
                        'openid' => $value['openid'],
                        'day' => $fullbackgoods['day'],
                        'fullbacktime' => strtotime('+'.$fullbackgoods['startday'].' days'),
                        'goodsid' => $value['goodsid'],
                        'createtime' => time()
                    );
                    if($fullbackgoods['type']>0){
                        $data['price'] =  $value['price'] * $fullbackgoods['minallfullbackallratio'] / 100;
                        $data['priceevery'] = $value['price'] * $fullbackgoods['fullbackratio'] / 100;
                    }else{
                        $data['price'] = $value['total'] * $fullbackgoods['minallfullbackallprice'];
                        $data['priceevery'] = $value['total'] * $fullbackgoods['fullbackprice'];
                    }
                    $condition = 'uniacid=:uniacid AND openid =:openid AND orderid=:orderid AND goodsid=:goodsid';
                    $params = array(':uniacid' => $uniacid,':openid' => $data['openid'], ':orderid' => $orderid,':goodsid' => $data['goodsid']);
                    $has_record = pdo_fetch("select id from ".tablename('ewei_shop_fullback_log')." where {$condition}",$params);
                    //防止重复记录数据
                    if(empty($has_record)){
                        pdo_insert('ewei_shop_fullback_log', $data);
                    }
                }
            }

        }
    }
    /*
     * 停止全返，返还余额
     * */
    function fullbackstop($orderid){
        global $_W,$_S;
        $uniacid = $_W['uniacid'];
        $shopset = $_S['shop'];

        $fullback_log = pdo_fetch("select * from ".tablename('ewei_shop_fullback_log')." where uniacid = ".$uniacid." and orderid = ".$orderid." ");

        /*$credit = 0;
        if($fullback_log['fullbackday']==$fullback_log['day']){
            $credit = $fullback_log['price'];
        }else{
            $credit = $fullback_log['priceevery'] * $fullback_log['fullbackday'];
        }*/
        /*if($credit>0){
            m('member')->setCredit($fullback_log['openid'], 'credit2', -$credit, array(0, $shopset['name'] . "退款扣除全返余额: {$credit} "));
        }*/
        pdo_update('ewei_shop_fullback_log', array('isfullback' => 1), array('id' => $fullback_log['id'], 'uniacid' => $uniacid));

    }
    /**
     * 支付成功
     * @global type $_W
     * @param type $params
     */
    public function payResult($params)
    {

        global $_W;
        $fee = intval($params['fee']);
        $data = array('status' => $params['result'] == 'success' ? 1 : 0);

        $ordersn_tid = $params['tid'];
        $ordersn = rtrim($ordersn_tid, 'TR');

        $order = pdo_fetch('select id,uniacid,ordersn, price,openid,dispatchtype,addressid,carrier,status,isverify,deductcredit2,`virtual`,isvirtual,couponid,isvirtualsend,isparent,paytype,merchid,agentid,createtime,buyagainprice,istrade,tradestatus,iscycelbuy from ' . tablename('ewei_shop_order') . ' where  ordersn=:ordersn and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':ordersn' => $ordersn));
        $plugincoupon = com('coupon');
        if ($plugincoupon) {
            $plugincoupon->useConsumeCoupon($order['id']);
        }
        //如果订单状态为已付款
        if($order['status']>=1){
            return true;
        }

        $orderid = $order['id'];
        $ispeerpay = $this->checkpeerpay($orderid);
        if (!empty($ispeerpay)){
            $peerpay_info = (float)pdo_fetchcolumn("select SUM(price) price from " . tablename('ewei_shop_order_peerpay_payinfo') . ' where pid=:pid limit 1', array(':pid' => $ispeerpay['id']));
            if ($peerpay_info < $ispeerpay['peerpay_realprice']){
                return;
            }
            pdo_update('ewei_shop_order',array('status'=>0),array('id'=>$order['id']));$order['status'] = 0;
            pdo_update('ewei_shop_order_peerpay',array('status'=>1),array('id'=>$ispeerpay['id']));
            $params['type'] = 'peerpay';
        }



        if ($params['from'] == 'return') {

            //秒杀
            $seckill_result = plugin_run('seckill::setOrderPay', $order['id']);

            if($seckill_result=='refund'){
                return 'seckill_refund';
            }

            $address = false;
            if (empty($order['dispatchtype'])) {
                $address = pdo_fetch('select realname,mobile,address from ' . tablename('ewei_shop_member_address') . ' where id=:id limit 1', array(':id' => $order['addressid']));
            }

            $carrier = false;
            if ($order['dispatchtype'] == 1 || $order['isvirtual'] == 1) {
                $carrier = unserialize($order['carrier']);
            }

            //创建记次时商品记录
            m('verifygoods')->createverifygoods($order['id']);

            if ($params['type'] == 'cash') {

                if ($order['isparent'] == 1) {
                    $change_data = array();
                    $change_data['merchshow'] = 1;

                    //订单状态
                    pdo_update('ewei_shop_order', $change_data, array('id' => $order['id']));

                    //处理子订单状态
                    $this->setChildOrderPayResult($order, 0, 0);
                }
                return true;
            } else {
                if ($order['istrade'] == 0) {
                    if ($order['status'] == 0) {
                        if (!empty($order['virtual']) && com('virtual')) {
                            return com('virtual')->pay($order,$ispeerpay);
                        } else if ($order['isvirtualsend']) {
                            return $this->payVirtualSend($order['id'],$ispeerpay);
                        } else {

                            $isonlyverifygoods = $this->checkisonlyverifygoods($order['id']);


                            $time = time();
                            $change_data = array();

                            if($isonlyverifygoods){
                                $change_data['status'] = 2;
                            }else{
                                $change_data['status'] = 1;
                            }
                            $change_data['paytime'] = $time;

                            if ($order['isparent'] == 1) {
                                $change_data['merchshow'] = 1;
                            }

                            //订单状态
                            pdo_update('ewei_shop_order', $change_data, array('id' => $order['id']));

                            if($order['iscycelbuy'] == 1){
                                if(p('cycelbuy')){
                                    p('cycelbuy') -> cycelbuy_periodic($order['id']);
                                }

                            }
                            if ($order['isparent'] == 1) {
                                //处理子订单状态
                                $this->setChildOrderPayResult($order, $time, 1);
                            }

                            //处理余额抵扣,下单时余额抵扣已经扣除,这里无需再执行
                            /*if ($order['deductcredit2'] > 0) {
                                $shopset = m('common')->getSysset('shop');
                                m('member')->setCredit($order['openid'], 'credit2', -$order['deductcredit2'], array(0, $shopset['name'] . "余额抵扣: {$order['deductcredit2']} 订单号: " . $order['ordersn']));
                            }*/

                            //处理积分与库存
                            $this->setStocksAndCredits($orderid, 1);

                            //发送赠送优惠券
                            if (com('coupon')) {
                                com('coupon')->sendcouponsbytask($order['id']); //订单支付
                                com('coupon')->backConsumeCoupon($order['id']); //订单支付
                            }


                            //如果有拆分订单则发送拆分后的订单通知
                            if ($order['isparent'] == 1) {
                                $child_list = $this->getChildOrder($order['id']);

                                foreach ($child_list as $k => $v) {
//                                    if (!empty($v['merchid'])) {
                                        //模板消息
                                        m('notice')->sendOrderMessage($v['id']);
                                    }
//                                }
                            }else{
                                //模板消息
                                m('notice')->sendOrderMessage($order['id']);
                            }

                            if($order['isparent'] == 1){
                                $merchSql = 'SELECT id,merchid FROM '.tablename('ewei_shop_order').' WHERE uniacid = '.intval($order['uniacid']).' AND parentid = '.intval($order['id']);
                                $merchData = pdo_fetchall($merchSql);
                                foreach($merchData as $mk => $mv){
                                    //打印机打印
                                    com_run('printer::sendOrderMessage', $mv['id']);
                                }
                            }else{
                                //打印机打印
                                com_run('printer::sendOrderMessage', $order['id']);
                            }


                            //分销商
                            if (p('commission')) {
                                p('commission')->checkOrderPay($order['id']);
                            }


                            $this->afterPayResult($order,$ispeerpay);
                        }
                    }
                } else {
                    $time = time();
                    $change_data = array();
                    $count_ordersn = $this->countOrdersn($ordersn_tid);

                    if($order['status'] == 0 && $count_ordersn == 1) {
                        $change_data['status'] = 1;
                        $change_data['tradestatus'] = 1;
                        $change_data['paytime'] = $time;
                    } else if ($order['status'] == 1 && $order['tradestatus'] == 1 && $count_ordersn == 2) {
                        $change_data['tradestatus'] = 2;
                        $change_data['tradepaytime'] = $time;
                    }

                    //订单状态
                    pdo_update('ewei_shop_order', $change_data, array('id' => $order['id']));
                    if($order['status'] == 0 && $count_ordersn == 1) {
                        //模板消息
                        m('notice')->sendOrderMessage($order['id']);
                    }
                }
                return true;
            }
        }
        return false;
    }

    /**
     * 子订单支付成功
     * @global type $_W
     * @param type $order
     * @param type $time
     */
    function setChildOrderPayResult($order, $time, $type)
    {

        global $_W;

        $orderid = $order['id'];
        $list = $this->getChildOrder($orderid);

        if (!empty($list)) {
            $change_data = array();
            if ($type == 1) {
                $change_data['status'] = 1;
                $change_data['paytime'] = $time;
            }
            $change_data['merchshow'] = 0;

            foreach ($list as $k => $v) {
                //订单状态
                if ($v['status'] == 0) {
                    pdo_update('ewei_shop_order', $change_data, array('id' => $v['id']));
                }
            }
        }
    }

    /**
     * 设置订单支付方式
     * @global type $_W
     * @param type $orderid
     * @param type $paytype
     */

    function setOrderPayType($orderid, $paytype, $ordersn = '')
    {

        global $_W;


        $count_ordersn = 1;
        $change_data = array();

        if (!empty($ordersn)) {
            $count_ordersn = $this->countOrdersn($ordersn);
        }

        if ($count_ordersn == 2) {
            $change_data['tradepaytype'] = $paytype;
        } else {
            $change_data['paytype'] = $paytype;
        }

        pdo_update('ewei_shop_order', $change_data, array('id' => $orderid));
        if (!empty($orderid)) {
            pdo_update('ewei_shop_order', array('paytype' => $paytype), array('parentid' => $orderid));
        }
    }

    /**
     * 获取子订单
     * @global type $_W
     * @param type $orderid
     */
    function getChildOrder($orderid)
    {

        global $_W;

        $list = pdo_fetchall('select id,ordersn,status,finishtime,couponid,merchid  from ' . tablename('ewei_shop_order') . ' where  parentid=:parentid and uniacid=:uniacid', array(':parentid' => $orderid, ':uniacid' => $_W['uniacid']));
        return $list;
    }


    /**
     * 虚拟商品自动发货
     * @param int $orderid
     * @return bool?
     */
    function payVirtualSend($orderid = 0,$ispeerpay=false) {

        global $_W, $_GPC;

        $order = pdo_fetch('select id,uniacid,ordersn, price,openid,dispatchtype,addressid,carrier,status,isverify,deductcredit2,`virtual`,isvirtual,couponid,isvirtualsend,isparent,paytype,merchid,agentid,createtime,buyagainprice,istrade,tradestatus,iscycelbuy from ' . tablename('ewei_shop_order') . ' where  id=:id and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $orderid));
        $order_goods = pdo_fetch("select g.virtualsend,g.virtualsendcontent from " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
            . " where og.orderid=:orderid and og.uniacid=:uniacid limit 1", array(':uniacid' => $order['uniacid'], ':orderid' => $orderid));
        $time = time();
        //自动完成
        pdo_update('ewei_shop_order', array('virtualsend_info' => $order_goods['virtualsendcontent'], 'status' => '3', 'paytime' => $time, 'sendtime' => $time, 'finishtime' => $time), array('id' => $orderid));


        //处理余额抵扣,下单时余额抵扣已经扣除,这里无需再执行
        /*if ($order['deductcredit2'] > 0) {
            $shopset = m('common')->getSysset('shop');
            m('member')->setCredit($order['openid'], 'credit2', -$order['deductcredit2'], array(0, $shopset['name'] . "余额抵扣: {$order['deductcredit2']} 订单号: " . $order['ordersn']));
        }*/
        //商品全返
        $this->fullback($order['id']);
        //处理库存
        $this->setStocksAndCredits($orderid, 1);

        //处理积分
        $this->setStocksAndCredits($orderid, 3);

        //会员升级
        m('member')->upgradeLevel($order['openid'],$order['id']);

        //余额赠送
        $this->setGiveBalance($orderid, 1);

        //发送赠送优惠券
        if (com('coupon')) {
            com('coupon')->sendcouponsbytask($order['id']); //订单支付
        }

        //优惠券返利
        if (com('coupon') && !empty($order['couponid'])) {
            com('coupon')->backConsumeCoupon($order['id']); //订单支付
        }
        //模板消息
        m('notice')->sendOrderMessage($orderid);

        //打印机打印
        com_run('printer::sendOrderMessage', $orderid);

        //分销商
        if (p('commission')) {
            //付款后
            p('commission')->checkOrderPay($order['id']);
            //自动完成后
            p('commission')->checkOrderFinish($order['id']);
        }


        $this->afterPayResult($order,$ispeerpay);

        return true;
    }


    //任务中心&游戏系统订单处理
    function afterPayResult($order,$ispeerpay=false){

        if (p('task')){

            //余额抵扣加入金额计算
            if($order['deductcredit2'] > 0 ){
                $order['price'] = floatval($order['price']) + floatval($order['deductcredit2']);
            }
            //积分抵扣加入金额计算
            if($order['deductcredit'] > 0){
                $order['price'] = floatval($order['price']) + floatval($order['deductprice']);
            }

            if ($order['agentid']){
                p('task')->checkTaskReward('commission_order',1);//分销订单
            }
            p('task')->checkTaskReward('cost_total',$order['price']);
            p('task')->checkTaskReward('cost_enough',$order['price']);
            p('task')->checkTaskReward('cost_count',1);
            $goodslist = pdo_fetchall("SELECT goodsid FROM ".tablename('ewei_shop_order_goods')." WHERE orderid = :orderid AND uniacid = :uniacid",array(':orderid'=>$order['id'], ':uniacid'=>$order['uniacid']));
            foreach($goodslist as $item) {
                p('task')->checkTaskReward('cost_goods'.$item['goodsid'],1,$order['openid']);
            }

            //余额抵扣加入金额计算
            if($order['deductcredit2'] > 0 ){
                $order['price'] = floatval($order['price']) + floatval($order['deductcredit2']);
            }
            //积分抵扣加入金额计算
            if($order['deductcredit'] > 0){
                $order['price'] = floatval($order['price']) + floatval($order['deductprice']);
            }

            //订单满额
//            p('task')->checkTaskProgress($order['price'],'order_full','',$order['openid']);//??这个需要移动到确认收货
            p('task')->checkTaskProgress($order['price'],'order_all','',$order['openid']);
            //购买指定商品
            $goodslist = pdo_fetchall("SELECT goodsid FROM ".tablename('ewei_shop_order_goods')." WHERE orderid = :orderid AND uniacid = :uniacid",array(':orderid'=>$order['id'], ':uniacid'=>$order['uniacid']));
            foreach($goodslist as $item) {
                p('task')->checkTaskProgress(1,'goods',0,$order['openid'],$item['goodsid']);
            }
            //首次购物
            if (pdo_fetchcolumn("select count(*) from ".tablename('ewei_shop_order')." where openid = '{$order['openid']}' and uniacid = {$order['uniacid']}")==1){
                p('task')->checkTaskProgress(1,'order_first','',$order['openid']);
            }
        }

        //抽奖模块
        if(p('lottery')&&empty($ispeerpay)){
            //余额抵扣加入金额计算
            if($order['deductcredit2'] > 0 ){
                $order['price'] = floatval($order['price']) + floatval($order['deductcredit2']);
            }
            //积分抵扣加入金额计算
            if($order['deductcredit'] > 0){
                $order['price'] = floatval($order['price']) + floatval($order['deductprice']);
            }

            //type 1:消费 2:签到 3:任务 4:其他
            $res = p('lottery')->getLottery($order['openid'],1,array('money'=>$order['price'],'paytype'=>1));

            if($res){
                //发送模版消息
                p('lottery')->getLotteryList($order['openid'],array('lottery_id'=>$res));
            }
        }
    }






    /**
     * 计算订单中商品累计赠送的积分
     * @param type $order
     */
    function getGoodsCredit($goods)
    {
        global $_W;

        $credits = 0;

        foreach ($goods as $g) {
            //积分累计
            $gcredit = trim($g['credit']);
            if (!empty($gcredit)) {
                if (strexists($gcredit, '%')) {
                    //按比例计算
                    $credits += intval(floatval(str_replace('%', '', $gcredit)) / 100 * $g['realprice']);
                } else {
                    //按固定值计算
                    $credits += intval($g['credit']) * $g['total'];
                }
            }
        }
        return $credits;
    }


    /**
     * 返还抵扣的余额
     * @param type $order
     */
    function setDeductCredit2($order)
    {
        global $_W;

        if ($order['deductcredit2'] > 0) {
            m('member')->setCredit($order['openid'], 'credit2', $order['deductcredit2'], array('0', $_W['shopset']['shop']['name'] . "购物返还抵扣余额 余额: {$order['deductcredit2']} 订单号: {$order['ordersn']}"));
        }
    }


    /**
     * 处理赠送余额情况
     * @param type $orderid
     * @param type $type 1 订单完成 2 售后
     */
    function setGiveBalance($orderid = '', $type = 0)
    {
        global $_W;
        $order = pdo_fetch('select id,ordersn,price,openid,dispatchtype,addressid,carrier,status from ' . tablename('ewei_shop_order') . ' where id=:id limit 1', array(':id' => $orderid));
        $goods = pdo_fetchall("select og.goodsid,og.total,g.totalcnf,og.realprice,g.money,og.optionid,g.total as goodstotal,og.optionid,g.sales,g.salesreal from " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
            . " where og.orderid=:orderid and og.uniacid=:uniacid ", array(':uniacid' => $_W['uniacid'], ':orderid' => $orderid));

        $balance = 0;

        foreach ($goods as $g) {
            //余额累计
            $gbalance = trim($g['money']);
            if (!empty($gbalance)) {
                if (strexists($gbalance, '%')) {
                    //按比例计算
                    $balance += round(floatval(str_replace('%', '', $gbalance)) / 100 * $g['realprice'], 2);
                } else {
                    //按固定值计算
                    $balance += round($g['money'], 2) * $g['total'];
                }
            }
        }

        //用户余额
        if ($balance > 0) {
            $shopset = m('common')->getSysset('shop');

            if ($type == 1) {
                //订单完成赠送余额
                if ($order['status'] == 3) {
                    m('member')->setCredit($order['openid'], 'credit2', $balance, array(0, $shopset['name'] . '购物赠送余额 订单号: ' . $order['ordersn']));
                }
            } elseif ($type == 2) {
                //订单售后,扣除赠送的余额
                if ($order['status'] >= 1) {
                    m('member')->setCredit($order['openid'], 'credit2', -$balance, array(0, $shopset['name'] . '购物取消订单扣除赠送余额 订单号: ' . $order['ordersn']));
                }
            }
        }
    }


    /**
     * //处理订单库存及用户积分情况(赠送积分)
     * @param type $orderid
     * @param type $type 0 下单 1 支付 2 取消 3 确认收货
     * @param $flag $flag 代表是不是执行增加积分的方式,如果有读写分离会导致订单状态还未改变就进来了
     */
    function setStocksAndCredits($orderid = '', $type = 0, $flag = false)
    {
        global $_W;

        $order = pdo_fetch('select id,ordersn,price,openid,dispatchtype,addressid,carrier,status,isparent,paytype,isnewstore,storeid,istrade,status from ' . tablename('ewei_shop_order') . ' where id=:id limit 1', array(':id' => $orderid));

        if (!empty($order['istrade'])) {
            return;
        }

        if (empty($order['isnewstore'])) {
            $newstoreid = 0;
        } else {
            $newstoreid = intval($order['storeid']);
        }

        $param = array();
        $param[':uniacid'] = $_W['uniacid'];

        if ($order['isparent'] == 1) {
            $condition = " og.parentorderid=:parentorderid";
            $param[':parentorderid'] = $orderid;
        } else {
            $condition = " og.orderid=:orderid";
            $param[':orderid'] = $orderid;
        }

        $goods = pdo_fetchall("select og.goodsid,og.seckill,og.total,g.totalcnf,og.realprice,g.credit,og.optionid,g.total as goodstotal,og.optionid,g.sales,g.salesreal,g.type from " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
            . " where $condition and og.uniacid=:uniacid ", $param);

        $credits = 0;
        foreach ($goods as $g) {


            if ($newstoreid > 0) {
                $store_goods = m('store')->getStoreGoodsInfo($g['goodsid'], $newstoreid);
                if (empty($store_goods)) {
                    return;
                }
                $g['goodstotal'] = $store_goods['stotal'];
            } else {
                $goods_item = pdo_fetch("select total as goodstotal from" . tablename('ewei_shop_goods') . " where id=:id and uniacid=:uniacid limit 1", array(":id"=>$g['goodsid'],':uniacid'=>$_W['uniacid']));
                $g['goodstotal'] = $goods_item['goodstotal'];
            }

            $stocktype = 0; //0 不设置库存情况 -1 减少 1 增加
            if ($type == 0) {
                //如果是下单
                if ($g['totalcnf'] == 0) {
                    //少库存
                    $stocktype = -1;
                }
            } else if ($type == 1) {
                if ($g['totalcnf'] == 1) {
                    //少库存
                    $stocktype = -1;
                }
            } else if ($type == 2) {
                //取消订单
                if ($order['status'] >= 1) {
                    //如果已付款
                    if ($g['totalcnf']!= 2) {
                        //加库存
                        $stocktype = 1;
                    }
                } else {
                    //未付款，并且是下单减库存
                    if ($g['totalcnf'] == 0) {
                        //加库存
                        $stocktype = 1;
                    }
                }
            }
            if (!empty($stocktype)) {
                $data = m('common')->getSysset('trade');
                if(!empty($data['stockwarn']))
                {
                    $stockwarn = intval($data['stockwarn']);
                }else
                {
                    $stockwarn = 5;
                }

                if (!empty($g['optionid'])) {
                    //减少规格库存
                    $option = m('goods')->getOption($g['goodsid'], $g['optionid']);

                    if ($newstoreid > 0) {
                        $store_goods_option = m('store')->getOneStoreGoodsOption($g['optionid'], $g['goodsid'], $newstoreid);

                        if (empty($store_goods_option)) {
                            return;
                        }
                        $option['stock'] = $store_goods_option['stock'];
                    }



                    if (!empty($option) && $option['stock'] != -1) {
                        $stock = -1;
                        if ($stocktype == 1) {
                            //增加库存
                            $stock = $option['stock'] + $g['total'];
                        } else if ($stocktype == -1) {
                            //减少库存
                            $stock = $option['stock'] - $g['total'];
                            $stock <= 0 && $stock = 0;


                            if($stockwarn>=$stock && $newstoreid == 0)
                            {
                                m('notice')-> sendStockWarnMessage($g['goodsid'], $g['optionid']);
                            }

                        }
                        if ($stock != -1) {

                            if ($newstoreid > 0) {
                                pdo_update('ewei_shop_newstore_goods_option', array('stock' => $stock), array('uniacid' => $_W['uniacid'], 'goodsid' => $g['goodsid'], 'id' => $store_goods_option['id']));
                            } else {
                                pdo_update('ewei_shop_goods_option', array('stock' => $stock), array('uniacid' => $_W['uniacid'], 'goodsid' => $g['goodsid'], 'id' => $g['optionid']));
                            }
                        }
                    }
                }
                if (((!empty($g['goodstotal']) && $stocktype ==-1) || $stocktype ==1) && $g['goodstotal'] != -1) {
                    //减少商品总库存
                    $totalstock = -1;
                    if ($stocktype == 1) {
                        //增加库存
                        $totalstock = $g['goodstotal'] + $g['total'];
                    } else if ($stocktype == -1) {
                        //减少库存
                        $totalstock = $g['goodstotal'] - $g['total'];
                        $totalstock <= 0 && $totalstock = 0;


                        if($stockwarn>=$totalstock && $newstoreid == 0)
                        {
                            m('notice')-> sendStockWarnMessage($g['goodsid'], 0);
                        }
                    }
                    if ($totalstock != -1) {

                        if ($newstoreid > 0) {
                            pdo_update('ewei_shop_newstore_goods', array('stotal' => $totalstock), array('uniacid' => $_W['uniacid'], 'id' => $store_goods['id']));
                        } else {
                            pdo_update('ewei_shop_goods', array('total' => $totalstock), array('uniacid' => $_W['uniacid'], 'id' => $g['goodsid']));
                        }
                    }
                }
            }

            $isgoodsdata = m('common')->getPluginset('sale');
            $isgoodspoint = iunserializer($isgoodsdata['credit1']);
            if(!empty($isgoodspoint['isgoodspoint']) && $isgoodspoint['isgoodspoint'] == 1){
                //积分累计
                $gcredit = trim($g['credit']);
                //秒杀不送积分
                if($g['seckill']!=1){
                    if (!empty($gcredit)) {
                        if (strexists($gcredit, '%')) {
                            //按比例计算
                            $credits += intval(floatval(str_replace('%', '', $gcredit)) / 100 * $g['realprice']);
                        } else {
                            //按固定值计算
                            $credits += intval($g['credit']) * $g['total'];
                        }
                    }
                }

            }

            if ($type == 0) {
                //虚拟销量只要是拍下就加 || 如果是付款减库存,则付款才加销量
//                if ($g['totalcnf'] != 1) {
//                    pdo_update('ewei_shop_goods', array('sales' => $g['sales'] + $g['total']), array('uniacid' => $_W['uniacid'], 'id' => $g['goodsid']));
//                }
            } elseif ($type == 1) {
                //真实销量付款才加
                if ($order['status'] >= 1) {
//                    if ($g['totalcnf'] != 1) {
//                        pdo_update('ewei_shop_goods', array('sales' => $g['sales'] + $g['total']), array('uniacid' => $_W['uniacid'], 'id' => $g['goodsid']));
//                    }
                    //实际销量
                    $salesreal = pdo_fetchcolumn('select ifnull(sum(total),0) from ' . tablename('ewei_shop_order_goods') . ' og '
                        . ' left join ' . tablename('ewei_shop_order') . ' o on o.id = og.orderid '
                        . ' where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1', array(':goodsid' => $g['goodsid'], ':uniacid' => $_W['uniacid']));
                    pdo_update('ewei_shop_goods', array('salesreal' => $salesreal), array('id' => $g['goodsid']));

                    //支付成功,如果商品设置了送积分营销活动,则增加一条记录
                    $table_flag = pdo_tableexists('ewei_shop_order_buysend');
                    if($credits > 0 && $table_flag){
                        $send_data = array(
                            'uniacid'   => $_W['uniacid'],
                            'orderid'   => $orderid,
                            'openid'   => $order['openid'],
                            'credit'   => $credits,
                            'createtime'   => TIMESTAMP,
                        );
                        $send_record = pdo_fetch('SELECT * FROM '.tablename('ewei_shop_order_buysend').' WHERE orderid = :orderid AND uniacid = :uniacid AND openid = :openid',array(':orderid'=>$orderid,':uniacid'=>$_W['uniacid'],':openid'=>$order['openid']));
                        if($send_record){
                            pdo_update('ewei_shop_order_buysend', $send_data, array('id' => $send_record['id']));
                        }else{
                            pdo_insert('ewei_shop_order_buysend', $send_data);
                        }
                    }
                }
            }
        }

        //用户积分
        $table_flag = pdo_tableexists('ewei_shop_order_buysend');
        if($table_flag){
            $send_record = pdo_fetch('SELECT * FROM '.tablename('ewei_shop_order_buysend').' WHERE orderid = :orderid AND uniacid = :uniacid AND openid = :openid',array(':orderid'=>$orderid,':uniacid'=>$_W['uniacid'],':openid'=>$order['openid']));
            if($send_record && ($send_record['credit'] > 0)) $credits = $send_record['credit'];
        }
        if ($credits > 0) {
            $shopset = m('common')->getSysset('shop');
            if ($type == 3) {
                if($order['status'] == 3 || $flag == true){
                    //支付增加积分
                    m('member')->setCredit($order['openid'], 'credit1', $credits, array(0, $shopset['name'] . '购物积分 订单号: ' . $order['ordersn']));
                    m('notice')->sendMemberPointChange($order['openid'],$credits,0,3);
                }
            } elseif ($type == 2) {
                //减少积分，只有订单完成才减少
                if ($order['status'] == 3) {
                    m('member')->setCredit($order['openid'], 'credit1', -$credits, array(0, $shopset['name'] . '购物取消订单扣除积分 订单号: ' . $order['ordersn']));
                    m('notice')->sendMemberPointChange($order['openid'],$credits,1,3);
                }
            }
        }else{
            //积分活动订单送积分
            if ($type == 3) {
                if($order['status'] == 3) {
                    //支付增加积分
                    $money = com_run('sale::getCredit1', $order['openid'], (float)$order['price'], $order['paytype'], 1);
                    if ($money > 0) {
                        m('notice')->sendMemberPointChange($order['openid'], $money, 0,3);
                    }
                }
            } elseif ($type == 2) {
                //减少积分，只有付款了才减少
                if ($order['status'] ==3) {
                    $money = com_run('sale::getCredit1',$order['openid'],(float)$order['price'],$order['paytype'],1,1);
                    if($money>0) {
                        m('notice')->sendMemberPointChange($order['openid'], $money, 1,3);
                    }
                }
            }
        }
    }

    function getTotals($merch = 0)
    {
        global $_W;

        $paras = array(':uniacid' => $_W['uniacid']);
        $merch = intval($merch);
        $condition = ' and isparent=0';
        if ($merch < 0) {
            $condition .= ' and merchid=0';
        }
        $totals['all'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0 and deleted=0", $paras);
        $totals['status_1'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0 and status=-1 and refundtime=0 and deleted=0", $paras);
        $totals['status0'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0  and status=0 and paytype<>3 and deleted=0", $paras);
        $totals['status1'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0  and ( status=1 or ( status=0 and paytype=3) ) and deleted=0", $paras);
        $totals['status2'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0  and ( status=2 or (status = 1 and sendtype > 0) ) and deleted=0", $paras);
        $totals['status3'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0  and status=3 and deleted=0", $paras);
        $totals['status4'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0  and (refundstate>0 and refundid<>0 or (refundtime=0 and refundstate=3)) and deleted=0", $paras);
        $totals['status5'] = pdo_fetchcolumn(
            'SELECT COUNT(1) FROM ' . tablename('ewei_shop_order') . ""
            . " WHERE uniacid = :uniacid {$condition} and ismr=0 and refundtime<>0 and deleted=0", $paras);

        return $totals;
    }

    function getFormartDiscountPrice($isd, $gprice, $gtotal = 1)
    {
        $price = $gprice;
        if (!empty($isd)) {
            if (strexists($isd, '%')) {
                //促销折扣
                $dd = floatval(str_replace('%', '', $isd));

                if ($dd > 0 && $dd < 100) {
                    $price = round($dd / 100 * $gprice, 2);
                }
            } else if (floatval($isd) > 0) {
                //促销价格
                $price = round(floatval($isd * $gtotal), 2);
            }
        }
        return $price;
    }


    //获得d商品详细促销
    function getGoodsDiscounts($goods, $isdiscount_discounts, $levelid, $options = array())
    {

        $key = empty($levelid) ? 'default' : 'level' . $levelid;
        $prices = array();

        if (empty($goods['merchsale'])) {
            if (!empty($isdiscount_discounts[$key])) {
                foreach ($isdiscount_discounts[$key] as $k => $v) {
                    $k = substr($k, 6);
                    $op_marketprice = m('goods')->getOptionPirce($goods['id'], $k);
                    $gprice = $this->getFormartDiscountPrice($v, $op_marketprice);
                    $prices[] = $gprice;
                    if (!empty($options)) {
                        foreach ($options as $key => $value) {
                            if ($value['id'] == $k) {
                                $options[$key]['marketprice'] = $gprice;
                            }
                        }
                    }
                }
            }
        } else {
            if (!empty($isdiscount_discounts['merch'])) {
                foreach ($isdiscount_discounts['merch'] as $k => $v) {
                    $k = substr($k, 6);
                    $op_marketprice = m('goods')->getOptionPirce($goods['id'], $k);
                    $gprice = $this->getFormartDiscountPrice($v, $op_marketprice);
                    $prices[] = $gprice;
                    if (!empty($options)) {
                        foreach ($options as $key => $value) {
                            if ($value['id'] == $k) {
                                $options[$key]['marketprice'] = $gprice;
                            }
                        }
                    }
                }
            }
        }

        $data = array();
        $data['prices'] = $prices;
        $data['options'] = $options;

        return $data;
    }

    //获得d商品促销或会员折扣价格
    function getGoodsDiscountPrice($g, $level, $type = 0)
    {
        global $_W;

        // 判断会员等级状态
        if(!empty($level['id'])){
            $level = pdo_fetch('select * from ' . tablename('ewei_shop_member_level') . ' where id=:id and uniacid=:uniacid and enabled=1 limit 1', array(':id' =>$level['id'], ':uniacid' => $_W['uniacid']));
            $level = empty($level)? array(): $level;
        }

        //商品原价
        if ($type == 0) {
            $total = $g['total'];
        } else {
            $total = 1;
        }

        $gprice = $g['marketprice'] * $total;

        if (empty($g['buyagain_islong'])) {
            $gprice = $g['marketprice'] * $total;
        }
        //重复购买购买是否享受其他折扣
        $buyagain_sale = true;
        $buyagainprice = 0;
        $canbuyagain = false;

        if (empty($g['is_task_goods'])) {
            if (floatval($g['buyagain']) > 0) {
                //第一次后买东西享受优惠
                if (m('goods')->canBuyAgain($g)) {
                    $canbuyagain = true;
                    if (empty($g['buyagain_sale'])) {
                        $buyagain_sale = false;
                    }
                }
            }
        }


        //成交的价格
        $price = $gprice;
        $price1 = $gprice;
        $price2 = $gprice;

        //任务活动物品
        $taskdiscountprice = 0; //任务活动折扣
        $lotterydiscountprice = 0; //游戏活动折扣
        if (!empty($g['is_task_goods'])) {
            $buyagain_sale = false;
            $price = $g['task_goods']['marketprice'] * $total;

            if ($gprice > $price) {
                $d_price = abs($gprice - $price);

                if ($g['is_task_goods'] == 1) {
                    $taskdiscountprice = $d_price;
                } else if ($g['is_task_goods'] == 2) {
                    $lotterydiscountprice = $d_price;
                }
            }
        }

        $discountprice = 0; //会员折扣
        $isdiscountprice = 0; //促销折扣
        $isd = false;
        @$isdiscount_discounts = json_decode($g['isdiscount_discounts'], true);

        //判断最终价格以哪种优惠计算 0 无优惠,1 促销优惠, 2 会员折扣
        $discounttype = 0;
        //判断是否有促销折扣
        $isCdiscount = 0;
        //判断是否有会员折扣
        $isHdiscount = 0;

        //是否有促销
        if ($g['isdiscount']==1 && $g['isdiscount_time'] >= time() && $buyagain_sale) {

            if (is_array($isdiscount_discounts)) {
                $key = !empty($level['id']) ? 'level' . $level['id'] : 'default';
                if (!isset($isdiscount_discounts['type']) || empty($isdiscount_discounts['type'])) {
                    //统一
                    if (empty($g['merchsale'])) {
                        $isd = trim($isdiscount_discounts[$key]['option0']);
                        if (!empty($isd)) {
                            $price1 = $this->getFormartDiscountPrice($isd, $gprice, $total);
                        }
                    } else {
                        $isd = trim($isdiscount_discounts['merch']['option0']);
                        if (!empty($isd)) {
                            $price1 = $this->getFormartDiscountPrice($isd, $gprice, $total);
                        }
                    }
                } else {
                    //详细促销
                    if (empty($g['merchsale'])) {
                        $isd = trim($isdiscount_discounts[$key]['option' . $g['optionid']]);
                        if (!empty($isd)) {
                            $price1 = $this->getFormartDiscountPrice($isd, $gprice, $total);
                        }
                    } else {
                        $isd = trim($isdiscount_discounts['merch']['option' . $g['optionid']]);
                        if (!empty($isd)) {
                            $price1 = $this->getFormartDiscountPrice($isd, $gprice, $total);
                        }
                    }
                }
            }

            //判断促销价是否低于原价
            if ($price1 >= $gprice) {
                $isdiscountprice = 0;
                $isCdiscount = 0;
            } else {
                $isdiscountprice = abs($price1 - $gprice);
                $isCdiscount = 1;
            }

        }

        if (empty($g['isnodiscount']) && $buyagain_sale) {
            //参与会员折扣
            $discounts = json_decode($g['discounts'], true);

            //如果是多商户商品，并且会员等级折扣为空的情况下，模拟空的商品会员等级折扣数据，以便计算折扣
            if(empty($g['discounts']) && $g['merchid']>0){
                $g['discounts']=array(
                    'type'=>'0',
                    'default'=>'',
                    'default_pay'=>''
                );
                if(!empty($level)){
                    $g['discounts']['level'.$level['id']]='';
                    $g['discounts']['level'.$level['id'].'_pay']='';
                }
                $discounts=$g['discounts'];
            }

            if (is_array($discounts)) {

                $key = !empty($level['id']) ? 'level' . $level['id'] : 'default';
                if (!isset($discounts['type']) || empty($discounts['type'])) {
                    //统一折扣
                    if (!empty($discounts[$key])) {
                        $dd = floatval($discounts[$key]); //设置的会员折扣
                        if ($dd > 0 && $dd < 10) {
                            $price2 = round($dd / 10 * $gprice, 2);
                        }
                    } else {
                        $dd = floatval($discounts[$key . '_pay'] * $total); //设置的会员折扣
                        $md = floatval($level['discount']); //会员等级折扣
                        if (!empty($dd)) {
                            $price2 = round($dd, 2);
                        } else if ($md > 0 && $md < 10) {
                            $price2 = round($md / 10 * $gprice, 2);
                        }
                    }
                } else {
                    //详细折扣

                    $isd = trim($discounts[$key]['option' . $g['optionid']]);
                    if (!empty($isd)) {
                        $price2 = $this->getFormartDiscountPrice($isd, $gprice, $total);
                    }
                }
            }

            //判断促销价是否低于原价
            if ($price2 >= $gprice) {
                $discountprice = 0;
                $isHdiscount = 0;
            } else {
                $discountprice = abs($price2 - $gprice);
                $isHdiscount = 1;
            }
        }

        if ($isCdiscount == 1) {
            $price = $price1;
            $discounttype = 1;
        } else if ($isHdiscount == 1) {
            $price = $price2;
            $discounttype = 2;
        }



        //平均价格
        $unitprice = round($price / $total, 2);
        //使用促销的减免价格
        $isdiscountunitprice = round($isdiscountprice / $total, 2);
        //使用会员折扣的减免价格
        $discountunitprice = round($discountprice / $total, 2);

        if ($canbuyagain) {
            if (empty($g['buyagain_islong'])) {
                $buyagainprice = $unitprice * (10 - $g['buyagain']) / 10;
            } else {
                $buyagainprice = $price * (10 - $g['buyagain']) / 10;
            }
        }

        $price = $price - $buyagainprice;

        return array(
            'unitprice' => $unitprice,
            'price' => $price,
            'taskdiscountprice' => $taskdiscountprice,
            'lotterydiscountprice' => $lotterydiscountprice,
            'discounttype' => $discounttype,
            'isdiscountprice' => $isdiscountprice,
            'discountprice' => $discountprice,
            'isdiscountunitprice' => $isdiscountunitprice,
            'discountunitprice' => $discountunitprice,
            'price0' => $gprice,
            'price1' => $price1,
            'price2' => $price2,
            'buyagainprice' => $buyagainprice
        );
    }

    //计算子订单中的相关费用
    function getChildOrderPrice(&$order, &$goods, &$dispatch_array, $merch_array, $sale_plugin, $discountprice_array,$orderid=0)
    {
        global $_GPC;
        $tmp_goods = $goods;
        //是兑换中心订单
        $is_exchange = (p('exchange') && $_SESSION['exchange']);
        if ($is_exchange){
            foreach ($dispatch_array['dispatch_merch'] as &$dispatch_merch){
                $dispatch_merch = 0;
            }
            unset($dispatch_merch);
            $postage = $_SESSION['exchange_postage_info'];
            $exchangepriceset = (array)$_SESSION['exchangepriceset'];
            foreach ($goods as $gk=> $one_goods){
                $goods[$gk]['ggprice'] = 0;
                $tmp_goods[$gk]['marketprice'] = 0;
            }
            foreach ($exchangepriceset as $pset){
                foreach ($goods as $gk=> &$one_goods){
                    if ($one_goods['ggprice'] == 0 && ($one_goods['optionid'] == $pset[0] || $one_goods['goodsid'] == $pset[0])){
                        $one_goods['ggprice'] += $pset[2];
                        $tmp_goods[$gk]['marketprice'] += $pset[2];
                        break;
                    }
                }
                unset($one_goods);
            }
        }
        $totalprice = $order['price'];             //总价
        $goodsprice = $order['goodsprice'];       //商品总价
        $grprice = $order['grprice'];             //商品实际总价

        $deductprice = $order['deductprice'];     //抵扣的钱
        $deductcredit = $order['deductcredit'];   //抵扣需要扣除的积分
        $deductcredit2 = $order['deductcredit2']; //可抵扣的余额

        $deductenough = $order['deductenough'];   //满额减
        //$couponprice = $order['couponprice'];     //优惠券价格

        $is_deduct = 0;        //是否进行积分抵扣的计算
        $is_deduct2 = 0;       //是否进行余额抵扣的计算
        $deduct_total = 0;     //计算商品中可抵扣的总积分
        $deduct2_total = 0;    //计算商品中可抵扣的总余额

        $ch_order = array();

        if ($sale_plugin) {
            //积分抵扣
            if (!empty($_GPC['deduct'])) {
                $is_deduct = 1;
            }

            //余额抵扣
            if (!empty($_GPC['deduct2'])) {
                $is_deduct2 = 1;
            }
        }
        foreach ($goods as $gk=> &$g) {
            $merchid = $g['merchid'];

            $ch_order[$merchid]['goods'][] = $g['goodsid'];
            $ch_order[$merchid]['grprice'] += $g['ggprice'];
            $ch_order[$merchid]['goodsprice'] += $tmp_goods[$gk]['marketprice'] * $g['total'];
//            $g['proportion'] = round($g['ggprice'] / $grprice, 2);
            $ch_order[$merchid]['couponprice'] = $discountprice_array[$merchid]['deduct'];

            if ($is_deduct == 1) {
                //积分抵扣
                if ($g['manydeduct']) {
                    $deduct = $g['deduct'] * $g['total'];
                } else {
                    $deduct = $g['deduct'];
                }


                if($g['seckillinfo'] && $g['seckillinfo']['status']==0){
                    //秒杀不抵扣
                }else{
                    $deduct_total += $deduct;
                    $ch_order[$merchid]['deducttotal'] += $deduct;
                }

            }

            if ($is_deduct2 == 1) {
                //余额抵扣
                if ($g['deduct2'] == 0) {
                    //全额抵扣
                    $deduct2 = $g['ggprice'];
                } else if ($g['deduct2'] > 0) {

                    //最多抵扣
                    if ($g['deduct2'] > $g['ggprice']) {
                        $deduct2 = $g['ggprice'];
                    } else {
                        $deduct2 = $g['deduct2'];
                    }
                }

                if($g['seckillinfo'] && $g['seckillinfo']['status']==0){
                    //秒杀不抵扣
                }else{
                    $ch_order[$merchid]['deduct2total'] += $deduct2;
                    $deduct2_total += $deduct2;
                }

            }
        }

        unset($g);

        foreach ($ch_order as $k => $v) {

            if ($is_deduct == 1) {
                //计算详细积分抵扣
                if ($deduct_total > 0) {
                    $n = $v['deducttotal'] / $deduct_total;
                    $deduct_credit = ceil(round($deductcredit * $n, 2));
                    $deduct_money = round($deductprice * $n, 2);
                    $ch_order[$k]['deductcredit'] = $deduct_credit;
                    $ch_order[$k]['deductprice'] = $deduct_money;
                }
            }

            if ($is_deduct2 == 1) {
                //计算详细余额抵扣
                if ($deduct2_total > 0) {
                    $n = $v['deduct2total'] / $deduct2_total;
                    $deduct_credit2 = round($deductcredit2 * $n, 2);
                    $ch_order[$k]['deductcredit2'] = $deduct_credit2;
                }
            }

            //子订单商品价格占总订单的比例
            $op = $grprice==0?0:round($v['grprice'] / $grprice, 2);
            $ch_order[$k]['op'] = $op;

            if ($deductenough > 0) {
                //计算满减金额
                $deduct_enough = round($deductenough * $op, 2);
                $ch_order[$k]['deductenough'] = $deduct_enough;
            }

        }

        if ($is_exchange){//兑换中心
            if (is_array($postage)){//按件计算运费
                foreach ($ch_order as $mid=> $ch) {
                    $flip = array_flip(array_flip($ch['goods']));
                    foreach ($flip as $gid){
                        $dispatch_array['dispatch_merch'][$mid] += $postage[$gid];
                    }
                }
            }else{//按单计算运费
                $old_dispatch_price = $order['dispatchprice'];
                $order['dispatchprice'] = $_SESSION['exchangepostage'] = $postage * count($dispatch_array['dispatch_merch']);
                pdo_update('ewei_shop_order',array('dispatchprice'=>$order['dispatchprice'],
                    'price'=>($order['price']+$order['dispatchprice']-$old_dispatch_price)),array('id'=>$orderid));
                foreach ($dispatch_array['dispatch_merch'] as &$dispatch_merch) {
                    $dispatch_merch = $postage;
                }
                unset($dispatch_merch);
            }
        }

        foreach ($ch_order as $k => $v) {
            $merchid = $k;
            $price = $v['grprice'] - $v['deductprice'] - $v['deductcredit2'] - $v['deductenough'] - $v['couponprice'] + $dispatch_array['dispatch_merch'][$merchid];

            //多商户满额减
            if ($merchid > 0) {
                $merchdeductenough = $merch_array[$merchid]['enoughdeduct'];
                if ($merchdeductenough > 0) {
                    $price -= $merchdeductenough;
                    $ch_order[$merchid]['merchdeductenough'] = $merchdeductenough;
                }
            }
            $ch_order[$merchid]['price'] = $price;
        }

        return $ch_order;

    }

    //计算订单中多商户满额减
    function getMerchEnough($merch_array)
    {
        $merch_enough_total = 0;

        $merch_saleset = array();

        foreach ($merch_array as $key => $value) {
            $merchid = $key;
            if ($merchid > 0) {
                $enoughs = $value['enoughs'];

                if (!empty($enoughs)) {
                    $ggprice = $value['ggprice'];

                    foreach ($enoughs as $e) {
                        if ($ggprice >= floatval($e['enough']) && floatval($e['money']) > 0) {
                            $merch_array[$merchid]['showenough'] = 1;
                            $merch_array[$merchid]['enoughmoney'] = $e['enough'];
                            $merch_array[$merchid]['enoughdeduct'] = $e['money'];

                            $merch_saleset['merch_showenough'] = 1;
                            $merch_saleset['merch_enoughmoney'] += $e['enough'];
                            $merch_saleset['merch_enoughdeduct'] += $e['money'];

                            $merch_enough_total += floatval($e['money']);
                            break;
                        }
                    }
                }
            }
        }

        $data = array();
        $data['merch_array'] = $merch_array;
        $data['merch_enough_total'] = $merch_enough_total;
        $data['merch_saleset'] = $merch_saleset;

        return $data;
    }


    //验证是否支持同城配送
    function validate_city_express($address){
        global $_W;

        $city_express_data=array(
            'state'=>0,//是否支持同城配送
            'enabled'=>0,//是否开启同城配送
            'price'=>0,//同城配送的运费
            'is_dispatch'=>1,//超出同城范围后是否使用快递
        );

        $city_express = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_city_express') . " WHERE uniacid=:uniacid and merchid=0 limit 1", array(':uniacid' => $_W['uniacid']));
        //没设置同城配送或者禁用时
        if(!empty($city_express['enabled'])){
            $city_express_data['enabled']=1;
            $city_express_data['is_dispatch']=$city_express['is_dispatch'];//超出同城范围后是否不能下单
            $city_express_data['is_sum']=$city_express['is_sum'];//多件商品时是否累加
            //有默认地址时根据地址逆解析经纬度，否则不支持
            if(!empty($address)){

                if(empty($address['lng']) || empty($address['lat'])){
                    //没有坐标时用会员地址地理逆解析
                    $data=m('util')->geocode($address['province'].$address['city'].$address['area'].$address['street'].$address['address'],$city_express['geo_key']);
                    if($data['status']==1 && $data['count']>0){
                        $location=explode(',',$data['geocodes'][0]['location']);

                        $addres=$address;
                        $addres['lng']=$location[0];
                        $addres['lat']=$location[1];
                        pdo_update('ewei_shop_member_address',$addres, array('id'=>$addres['id'],'uniacid' => $_W['uniacid']));
                        $city_express_data=$this->compute_express_price($city_express,$location[0],$location[1]);
                    }
                }else{
                    //否则直接用坐标
                    $city_express_data=$this->compute_express_price($city_express,$address['lng'],$address['lat']);
                }
            }
        }

        return $city_express_data;
    }

    //计算同城配送价格
    function compute_express_price($city_express,$lng,$lat){
        $city_express_data=array(
            'state'=>0,//是否支持同城配送
            'enabled'=>1,//是否开启同城配送
            'price'=>0,//同城配送的运费
            'is_dispatch'=>$city_express['is_dispatch'],//超出同城范围后是否不能下单
            'is_sum'=>$city_express['is_sum']//多件商品时是否累加
        );
        //计算两组坐标的距离
        $distance=m('util')->GetDistance($city_express['lat'],$city_express['lng'],$lat,$lng);

        //没有超出范围
        if($distance<$city_express['range']){
            $city_express_data['state']=1;
            //起步范围内
            if($distance<=($city_express['start_km']*1000)){
                $city_express_data['price']=intval($city_express['start_fee']);
            }
            //R1范围内,超出起步范围多少公里内,每增加1公里多少钱
            if($distance>($city_express['start_km']*1000) && $distance<=($city_express['start_km']*1000)+($city_express['pre_km']*1000)){
                $km=$distance-intval($city_express['start_km']*1000);//实际超出起步范围的公里数
                $city_express_data['price']=intval($city_express['start_fee']+($city_express['pre_km_fee']*ceil($km/1000)));
            }
            //R2范围内,超出多少公里，固定价格多少钱
            if($distance>=($city_express['fixed_km']*1000)){
                 $city_express_data['price']=intval($city_express['fixed_fee']);
            }
        }

        return $city_express_data;
    }
    //计算订单商品总运费
    function getOrderDispatchPrice($goods, $member, $address, $saleset = false, $merch_array, $t, $loop = 0)
    {

        global $_W;

        $area_set = m('util')->get_area_config_set();
        $new_area = intval($area_set['new_area']);

        $realprice = 0;
        $dispatch_price = 0;
        $dispatch_array = array();
        $dispatch_merch = array();
        $total_array = array();
        $totalprice_array = array();
        $nodispatch_array = array();
        $goods_num = count($goods);

        $seckill_payprice = 0;  //秒杀的金额
        $seckill_dispatchprice=0; //秒杀的邮费

        $user_city = '';
        $user_city_code = '';

        if (empty($new_area)) {
            if (!empty($address)) {
                $user_city = $user_city_code = $address['city'];
            } else if (!empty($member['city'])) {

                if(!strexists($member['city'],'市')){
                    $member['city']=$member['city'].'市';
                }
                $user_city = $user_city_code = $member['city'];
            }
        } else {
            if (!empty($address)) {
                $user_city = $address['city'].$address['area'];
                $user_city_code = $address['datavalue'];
            }
        }

        $is_merchid=0;//是否有多商户商品
        foreach ($goods as $g) {
            $realprice += $g['ggprice'];
            $dispatch_merch[$g['merchid']] = 0;
            $total_array[$g['goodsid']] += $g['total'];
            $totalprice_array[$g['goodsid']] += $g['ggprice'];
            if(!empty($g['merchid'])){
                $is_merchid=1;
            }
        }

        $city_express_data['state']=0;//是否支持同城配送0为不支持
        $city_express_data['enabled']=0;//是否开启同城配送0为未开启
        $city_express_data['is_dispatch']=1;//超出同城范围后是否使用快递
        //判断是否支持同城配送，多商户订单不考虑
        if($is_merchid==0){
            $city_express_data=$this->validate_city_express($address);
        }

        foreach ($goods as $g) {
            //秒杀
            $seckillinfo = plugin_run('seckill::getSeckill', $g['goodsid'], $g['optionid'], true, $_W['openid']);

            if ($seckillinfo && $seckillinfo['status'] == 0) {
                $seckill_payprice += $g['ggprice'];
            }

            //不配送状态 0配送 1不配送
            $isnodispatch = 0;

            //是否包邮
            $sendfree = false;
            $merchid = $g['merchid'];

            if($g['type']==5){
                $sendfree = true;
            }

            if (!empty($g['issendfree'])) { //本身包邮
                $sendfree = true;

            } else {

                if ($seckillinfo && $seckillinfo['status'] == 0) {
                    //秒杀不参与满件包邮
                } else {

                    if ($total_array[$g['goodsid']] >= $g['ednum'] && $g['ednum'] > 0) { //单品满件包邮

                        if (empty($new_area)) {
                            $gareas = explode(";", $g['edareas']);
                        } else {
                            $gareas = explode(";", $g['edareas_code']);
                        }

                        if (empty($gareas)) {
                            $sendfree = true;
                        } else {
                            if (!empty($address)) {
                                if (!in_array($user_city_code, $gareas)) {
                                    $sendfree = true;
                                }
                            } else if (!empty($member['city'])) {
                                if (!in_array($member['city'], $gareas)) {
                                    $sendfree = true;
                                }
                            } else {
                                $sendfree = true;
                            }
                        }
                    }
                }


                if ($seckillinfo && $seckillinfo['status'] == 0) {
                    //秒杀不参与满额包邮
                } else {
                    if ($totalprice_array[$g['goodsid']] >= floatval($g['edmoney']) && floatval($g['edmoney']) > 0) { //单品满额包邮

                        if (empty($new_area)) {
                            $gareas = explode(";", $g['edareas']);
                        } else {
                            $gareas = explode(";", $g['edareas_code']);
                        }

                        if (empty($gareas)) {
                            $sendfree = true;
                        } else {
                            if (!empty($address)) {
                                if (!in_array($user_city_code, $gareas)) {
                                    $sendfree = true;
                                }
                            } else if (!empty($member['city'])) {
                                if (!in_array($member['city'], $gareas)) {
                                    $sendfree = true;
                                }
                            } else {
                                $sendfree = true;
                            }
                        }
                    }
                }

            }


            //读取快递信息
            if ($g['dispatchtype'] == 1) {
                //使用统一邮费

                //不支持同城配送
                if($city_express_data['state']==0 && $city_express_data['is_dispatch']==1){
                    //是否设置了不配送城市
                    if (!empty($user_city)) {
                        if (empty($new_area)) {
                            $citys = m('dispatch')->getAllNoDispatchAreas();
                        } else {
                            $citys = m('dispatch')->getAllNoDispatchAreas('', 1);
                        }

                        if (!empty($citys)) {
                            if (in_array($user_city_code, $citys) && !empty($citys)) {
                                //如果此条包含不配送城市
                                $isnodispatch = 1;

                                $has_goodsid = 0;
                                if (!empty($nodispatch_array['goodid'])) {
                                    if (in_array($g['goodsid'], $nodispatch_array['goodid'])) {
                                        $has_goodsid = 1;
                                    }
                                }

                                if ($has_goodsid == 0) {
                                    $nodispatch_array['goodid'][] = $g['goodsid'];
                                    $nodispatch_array['title'][] = $g['title'];
                                    $nodispatch_array['city'] = $user_city;
                                }
                            }
                        }
                    }

                    if ($g['dispatchprice'] > 0 && !$sendfree && $isnodispatch == 0) {
                        //固定运费不累计
                        $dispatch_merch[$merchid] += $g['dispatchprice'];
                        if ($seckillinfo && $seckillinfo['status'] == 0) {
                            $seckill_dispatchprice += $g['dispatchprice'];
                        }else{
                            $dispatch_price += $g['dispatchprice'];
                        }
                    }
                //支持同城配送
                }else{

                    if($city_express_data['state']==1){
                        if($g['dispatchprice'] > 0 && !$sendfree){
                            //多件商品时是否累加
                            if($city_express_data['is_sum']==1){
                                $dispatch_price += $g['dispatchprice'];
                            }else{
                                //不累加时，取运费最大值
                                if($dispatch_price<$g['dispatchprice']){
                                    $dispatch_price =$g['dispatchprice'];
                                }
                            }
                        }
                    }else{
                            $nodispatch_array['goodid'][] = $g['goodsid'];
                            $nodispatch_array['title'][] = $g['title'];
                            $nodispatch_array['city'] = $user_city;
                    }
                }
            } else if ($g['dispatchtype'] == 0) {
                //使用快递模板

                //不支持同城配送
                if($city_express_data['state']==0 && $city_express_data['is_dispatch']==1){
                    if (empty($g['dispatchid'])) {
                        //默认快递
                        $dispatch_data = m('dispatch')->getDefaultDispatch($merchid);
                    } else {
                        $dispatch_data = m('dispatch')->getOneDispatch($g['dispatchid']);
                    }

                    if (empty($dispatch_data)) {
                        //最新的一条快递信息
                        $dispatch_data = m('dispatch')->getNewDispatch($merchid);
                    }

                    //是否设置了不配送城市
                    if (!empty($dispatch_data)) {
                        $isnoarea = 0;

                        $dkey = $dispatch_data['id'];
                        $isdispatcharea = intval($dispatch_data['isdispatcharea']);

//                    print_r($isdispatcharea);exit;

                        if (!empty($user_city)) {

                            if (empty($isdispatcharea)) {
                                if (empty($new_area)) {
                                    $citys = m('dispatch')->getAllNoDispatchAreas($dispatch_data['nodispatchareas']);
                                } else {
                                    $citys = m('dispatch')->getAllNoDispatchAreas($dispatch_data['nodispatchareas_code'], 1);
                                }

                                if (!empty($citys)) {
                                    if (in_array($user_city_code, $citys)) {
                                        //如果此条包含不配送城市
                                        $isnoarea = 1;
                                    }
                                }
                            } else {
                                if (empty($new_area)) {
                                    $citys = m('dispatch')->getAllNoDispatchAreas();
                                } else {
                                    $citys = m('dispatch')->getAllNoDispatchAreas('', 1);
                                }

                                if (!empty($citys)) {
                                    if (in_array($user_city_code, $citys)) {
                                        //如果此条包含全局不配送城市
                                        $isnoarea = 1;
                                    }
                                }

                                if (empty($isnoarea)) {
                                    $isnoarea = m('dispatch')->checkOnlyDispatchAreas($user_city_code, $dispatch_data);
                                }
                            }

                            if (!empty($isnoarea)) {
                                //包含不配送城市
                                $isnodispatch = 1;

                                $has_goodsid = 0;
                                if (!empty($nodispatch_array['goodid'])) {
                                    if (in_array($g['goodsid'], $nodispatch_array['goodid'])) {
                                        $has_goodsid = 1;
                                    }
                                }
                                if ($has_goodsid == 0) {
                                    $nodispatch_array['goodid'][] = $g['goodsid'];
                                    $nodispatch_array['title'][] = $g['title'];
                                    $nodispatch_array['city'] = $user_city;
                                }
                            }
                        }
                        if (!$sendfree && $isnodispatch == 0) {
                            //配送区域
                            $areas = unserialize($dispatch_data['areas']);
                            if ($dispatch_data['calculatetype'] == 1) {
                                //按件计费
                                $param = $g['total'];
                            } else {
                                //按重量计费
                                $param = $g['weight'] * $g['total'];
                            }
                            if (array_key_exists($dkey, $dispatch_array)) {
                                $dispatch_array[$dkey]['param'] += $param;
                            } else {
                                $dispatch_array[$dkey]['data'] = $dispatch_data;
                                $dispatch_array[$dkey]['param'] = $param;
                            }
                            if($seckillinfo && $seckillinfo['status']==0) {
                                if (array_key_exists($dkey, $dispatch_array)) {
                                    $dispatch_array[$dkey]['seckillnums'] += $param;
                                } else {
                                    $dispatch_array[$dkey]['seckillnums'] = $param;
                                }
                            }
                        }
                    }
                //支持同城配送
                }else{
                    if($city_express_data['state']==1) {
                            if (!$sendfree) {
                                //多件商品时是否累加
                                if ($city_express_data['is_sum'] == 1) {
                                    $dispatch_price += ($city_express_data['price']*$g['total']);
                                } else {
                                    //不累加时，取运费最大值
                                    if ($dispatch_price < $city_express_data['price']) {
                                        $dispatch_price = $city_express_data['price'];
                                    }
                                }
                            }
                    }else{
                        $nodispatch_array['goodid'][] = $g['goodsid'];
                        $nodispatch_array['title'][] = $g['title'];
                        $nodispatch_array['city'] = $user_city;
                    }
                }
            }
        }
        //最后比较模版快递和同城运费取最大值，使用快递模版的时候才比较
        if($city_express_data['state']==1 && $g['dispatchtype'] == 0){
            //不累加时，取运费最大值
            if($city_express_data['is_sum']==0 && $dispatch_price<$city_express_data['price']){
                $dispatch_price=$city_express_data['price'];
            }
        }

        if (!empty($dispatch_array)) {

            $dispatch_info = array();

            foreach ($dispatch_array as $k => $v) {
                $dispatch_data = $dispatch_array[$k]['data'];
                $param = $dispatch_array[$k]['param'];
                $areas = unserialize($dispatch_data['areas']);

                if (!empty($address)) {
                    //用户有默认地址
                    $dprice = m('dispatch')->getCityDispatchPrice($areas, $address, $param, $dispatch_data);
                    //获取当前地址在哪一个运费区间  auth: sunchao
                    $freeprice = m('dispatch')->getCityfreepricePrice($areas, $address);
                    if($freeprice>0){
                        $dispatch_data['freeprice'] = $freeprice;
                    }
                }
//                else if (!empty($member['city'])) {
//                    //设置了城市需要判断区域设置
//                    $dprice = m('dispatch')->getCityDispatchPrice($areas, $member, $param, $dispatch_data);
//                }
                else {
                    //如果会员还未设置城市 ，默认邮费
                    $dprice = m('dispatch')->getDispatchPrice($param, $dispatch_data);
                }


                $merchid = $dispatch_data['merchid'];
                $dispatch_merch[$merchid] += $dprice;

                if( $v['seckillnums']>0){
                    $seckill_dispatchprice+=$dprice;
                }else{
                    $dispatch_price += $dprice;
                }
                $dispatch_info[$dispatch_data['id']]['price'] += $dprice;
                $dispatch_info[$dispatch_data['id']]['freeprice'] = intval($dispatch_data['freeprice']);
            }

            if (!empty($dispatch_info)) {
                foreach ($dispatch_info as $k => $v) {
                    if($v['freeprice'] > 0 && $v['price'] >= $v['freeprice']) {
                        $dispatch_price -= $v['price'];
                    }
                }
                if ($dispatch_price < 0) {
                    $dispatch_price = 0;
                }
            }
        }
        //判断多商户是否满额包邮
        if (!empty($merch_array)) {

            foreach ($merch_array as $key => $value) {
                $merchid = $key;

                if ($merchid > 0) {
                    $merchset = $value['set'];
                    if (!empty($merchset['enoughfree'])) {
                        if (floatval($merchset['enoughorder']) <= 0) {
                            $dispatch_price = $dispatch_price - $dispatch_merch[$merchid];
                            $dispatch_merch[$merchid] = 0;
                        } else {
                            if ($merch_array[$merchid]['ggprice'] >= floatval($merchset['enoughorder'])) {
                                //订单大于设定的包邮金额
                                if (empty($merchset['enoughareas'])) {
                                    //如果不限制区域，包邮
                                    $dispatch_price = $dispatch_price - $dispatch_merch[$merchid];
                                    $dispatch_merch[$merchid] = 0;
                                } else {
                                    //如果限制区域
                                    $areas = explode(";", $merchset['enoughareas']);
                                    if (!empty($address)) {
                                        if (!in_array($address['city'], $areas)) {
                                            $dispatch_price = $dispatch_price - $dispatch_merch[$merchid];
                                            $dispatch_merch[$merchid] = 0;
                                        }
                                    } else if (!empty($member['city'])) {
                                        //设置了城市需要判断区域设置
                                        if (!in_array($member['city'], $areas)) {
                                            $dispatch_price = $dispatch_price - $dispatch_merch[$merchid];
                                            $dispatch_merch[$merchid] = 0;
                                        }
                                    } else if (empty($member['city'])) {
                                        //如果会员还未设置城市 ，默认邮费
                                        $dispatch_price = $dispatch_price - $dispatch_merch[$merchid];
                                        $dispatch_merch[$merchid] = 0;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        //营销宝满额包邮
        if ($saleset) {

            if (!empty($saleset['enoughfree'])) {

                //是否满足营销宝满额包邮
                $saleset_free = 0;

                if ($loop == 0) {
                    if (floatval($saleset['enoughorder']) <= 0) {
                        $saleset_free = 1;
                    } else {

                        if ($realprice - $seckill_payprice >= floatval($saleset['enoughorder'])) {
                            //订单大于设定的包邮金额
                            if (empty($saleset['enoughareas'])) {
                                //如果不限制区域，包邮
                                $saleset_free = 1;
                            } else {
                                //如果限制区域
                                if (empty($new_area)) {
                                    $areas = explode(";", trim($saleset['enoughareas'],";"));
                                } else {
                                    $areas = explode(";", trim($saleset['enoughareas_code'],";"));
                                }

                                if (!empty($user_city_code)) {
                                    if (!in_array($user_city_code, $areas)) {
                                        $saleset_free = 1;
                                    }
                                }
                            }
                        }
                    }
                }

                if ($saleset_free == 1) {
                    $is_nofree = 0;
                    $new_goods = array();
                    if (!empty($saleset['goodsids'])) {
                        foreach ($goods as $k => $v) {
                            if (!in_array($v['goodsid'], $saleset['goodsids'])) {
                                $new_goods[$k] = $goods[$k];
                                unset($goods[$k]);
                            } else {
                                $is_nofree = 1;
                            }
                        }
                    }

                    if ($is_nofree == 1 && $loop == 0) {
                        if ($goods_num == 1) {
                            $new_data1 = $this->getOrderDispatchPrice($goods, $member, $address, $saleset, $merch_array, $t, 1);
                            $dispatch_price = $new_data1['dispatch_price'];
                        } else {
                            $new_data2 = $this->getOrderDispatchPrice($new_goods, $member, $address, $saleset, $merch_array, $t, 1);
                            $dispatch_price = $dispatch_price - $new_data2['dispatch_price'];
                        }
                    } else {
                        if ($saleset_free == 1) {
                            $dispatch_price = 0;
                        }
                    }
                }
            }
        }

        if ($dispatch_price == 0) {
            foreach ($dispatch_merch as &$dm) {
                $dm = 0;
            }
            unset($dm);
        }

        if (!empty($nodispatch_array) && !empty($address)) {
            $nodispatch = '商品“ ';
            foreach ($nodispatch_array['title'] as $k => $v) {
                $nodispatch .= $v . ',';
            }
            $nodispatch = trim($nodispatch, ',');
            $nodispatch .= ' ”不支持配送到' . $nodispatch_array['city'];
            $nodispatch_array['nodispatch'] = $nodispatch;
            $nodispatch_array['isnodispatch'] = 1;
        }



        $data = array();

        $data['dispatch_price'] = $dispatch_price + $seckill_dispatchprice;
        $data['dispatch_merch'] = $dispatch_merch;
        $data['nodispatch_array'] = $nodispatch_array;
        $data['seckill_dispatch_price'] = $seckill_dispatchprice;
        $data['city_express_state'] = $city_express_data['state'];

        return $data;
    }

    //修改总订单的价格
    function changeParentOrderPrice($parent_order)
    {
        global $_W;

        $id = $parent_order['id'];
        $item = pdo_fetch("SELECT price,ordersn2,dispatchprice,changedispatchprice FROM " . tablename('ewei_shop_order') . " WHERE id = :id and uniacid=:uniacid", array(':id' => $id, ':uniacid' => $_W['uniacid']));

        if (!empty($item)) {
            $orderupdate = array();
            $orderupdate['price'] = $item['price'] + $parent_order['price_change'];
            $orderupdate['ordersn2'] = $item['ordersn2'] + 1;

            $orderupdate['dispatchprice'] = $item['dispatchprice'] + $parent_order['dispatch_change'];
            $orderupdate['changedispatchprice'] = $item['changedispatchprice'] + $parent_order['dispatch_change'];

            if (!empty($orderupdate)) {
                pdo_update('ewei_shop_order', $orderupdate, array('id' => $id, 'uniacid' => $_W['uniacid']));
            }
        }
    }

    //计算订单中的佣金
    function getOrderCommission($orderid, $agentid = 0)
    {
        global $_W;

        if (empty($agentid)) {
            $item = pdo_fetch('select agentid from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid Limit 1', array('id' => $orderid, ':uniacid' => $_W['uniacid']));
            if (!empty($item)) {
                $agentid = $item['agentid'];
            }
        }

        $level = 0;
        $pc = p('commission');
        if ($pc) {
            $pset = $pc->getSet();
            $level = intval($pset['level']);
        }

        $commission1 = 0;
        $commission2 = 0;
        $commission3 = 0;
        $m1 = false;
        $m2 = false;
        $m3 = false;
        if (!empty($level)) {
            if (!empty($agentid)) {
                $m1 = m('member')->getMember($agentid);
                if (!empty($m1['agentid'])) {
                    $m2 = m('member')->getMember($m1['agentid']);
                    if (!empty($m2['agentid'])) {
                        $m3 = m('member')->getMember($m2['agentid']);
                    }
                }
            }
        }

        //订单商品
        $order_goods = pdo_fetchall('select g.id,g.title,g.thumb,g.goodssn,og.goodssn as option_goodssn, g.productsn,og.productsn as option_productsn, og.total,og.price,og.optionname as optiontitle, og.realprice,og.changeprice,og.oldprice,og.commission1,og.commission2,og.commission3,og.commissions,og.diyformdata,og.diyformfields from ' . tablename('ewei_shop_order_goods') . ' og '
            . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid '
            . ' where og.uniacid=:uniacid and og.orderid=:orderid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $orderid));

        foreach ($order_goods as &$og) {

            if (!empty($level) && !empty($agentid)) {
                $commissions = iunserializer($og['commissions']);
                if (!empty($m1)) {
                    if (is_array($commissions)) {
                        $commission1 += isset($commissions['level1']) ? floatval($commissions['level1']) : 0;
                    } else {
                        $c1 = iunserializer($og['commission1']);
                        $l1 = $pc->getLevel($m1['openid']);
                        $commission1 += isset($c1['level' . $l1['id']]) ? $c1['level' . $l1['id']] : $c1['default'];
                    }
                }
                if (!empty($m2)) {
                    if (is_array($commissions)) {
                        $commission2 += isset($commissions['level2']) ? floatval($commissions['level2']) : 0;
                    } else {
                        $c2 = iunserializer($og['commission2']);
                        $l2 = $pc->getLevel($m2['openid']);
                        $commission2 += isset($c2['level' . $l2['id']]) ? $c2['level' . $l2['id']] : $c2['default'];
                    }
                }
                if (!empty($m3)) {
                    if (is_array($commissions)) {
                        $commission3 += isset($commissions['level3']) ? floatval($commissions['level3']) : 0;
                    } else {
                        $c3 = iunserializer($og['commission3']);
                        $l3 = $pc->getLevel($m3['openid']);
                        $commission3 += isset($c3['level' . $l3['id']]) ? $c3['level' . $l3['id']] : $c3['default'];
                    }
                }
            }
        }
        unset($og);

        $commission = $commission1 + $commission2 + $commission3;

        return $commission;
    }


    //检查订单中是否有下架商品
    function checkOrderGoods($orderid)
    {

        global $_W;
        $uniacid = $_W['uniacid'];
        $openid = $_W['openid'];
        $member = m('member')->getMember($openid, true);

        $flag = 0;
        $msg = '订单中的商品' . '<br/>';
        $uniacid = $_W['uniacid'];
        $ispeerpay = m('order')->checkpeerpay($orderid);//检查是否是代付订单

        $item = pdo_fetch("select * from ".tablename('ewei_shop_order')."  where  id = :id and uniacid=:uniacid limit 1",array(":id"=>$orderid,":uniacid"=>$uniacid));

        if((empty($order['isnewstore']) || empty($order['storeid']))&&empty($order['istrade']))
        {

            $order_goods = pdo_fetchall('select og.id,g.title, og.goodsid,og.optionid,g.total as stock,og.total as buycount,g.status,g.deleted,g.maxbuy,g.usermaxbuy,g.istime,g.timestart,g.timeend,g.buylevels,g.buygroups,g.totalcnf,og.seckill from  ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id '
                . ' where og.orderid=:orderid and og.uniacid=:uniacid ', array(':uniacid' => $_W['uniacid'], ':orderid' => $orderid));


            foreach ($order_goods as $data) {
                if (empty($data['status']) || !empty($data['deleted'])) {
                    $flag = 1;
                    $msg .= $data['title'] . '<br/> 已下架,不能付款!!';
                }

                $unit = empty($data['unit']) ? '件' : $data['unit'];
                $seckillinfo = plugin_run("seckill::getSeckill", $data['goodsid'], $data['optionid'], true, $_W['openid']);
                if ($seckillinfo && $seckillinfo['status'] == 0 || !empty($ispeerpay)) {
                    //如果是秒杀，不判断任何条件//代付订单也不判断
                }
                else {
                    if ($data['totalcnf'] == 1) {
                        if (!empty($data['optionid'])) {
                            $option = pdo_fetch('select id,title,marketprice,goodssn,productsn,stock,`virtual` from ' . tablename('ewei_shop_goods_option') . ' where id=:id and goodsid=:goodsid and uniacid=:uniacid  limit 1', array(':uniacid' => $uniacid, ':goodsid' => $data['goodsid'], ':id' => $data['optionid']));
                            if (!empty($option)) {
                                if ($option['stock'] != -1) {
                                    if (empty($option['stock'])) {
                                        $flag = 1;
                                        $msg .=  $data['title'] . "<br/>" . $option['title'] . " 库存不足!";
                                    }
                                }
                            }
                        } else {
                            if ($data['stock'] != -1) {
                                if (empty($data['stock'])) {
                                    $flag = 1;
                                    $msg .=  $data['title'] . "<br/>库存不足!";
                                }
                            }
                        }
                    }
                }
            }
        }else
        {
            if(p('newstore')){
                $sql = "select g.id,g.title,ng.gstatus,g.deleted"
                    . " from " . tablename('ewei_shop_order_goods') . " og left join  " . tablename('ewei_shop_goods') . " g  on g.id=og.goodsid and g.uniacid=og.uniacid"
                    . " inner join " . tablename('ewei_shop_newstore_goods') . " ng on ng.goodsid = g.id AND ng.storeid=" .$item['storeid']
                    . " where og.orderid=:orderid and og.uniacid=:uniacid";
                $list = pdo_fetchall($sql, array(':uniacid' => $uniacid, ':orderid' => $orderid));

                if (!empty($list)) {
                    foreach ($list as $k => $v) {
                        if (empty($v['gstatus']) || !empty($v['deleted'])) {
                            $flag = 1;
                            $msg .= $v['title'] . '<br/>';
                        }
                    }
                    if ($flag == 1) {
                        $msg .= '已下架,不能付款!';
                    }
                }
            }else{
                $flag = 1;
                $msg .= '门店歇业,不能付款!';
            }

        }




        $data = array();
        $data['flag'] = $flag;
        $data['msg'] = $msg;

        return $data;
    }

    public function checkpeerpay($orderid){//查询是否是代付订单,如果不是返回false,如果是返回代付订单内容
        global $_W;
        $sql = "SELECT p.*,o.openid FROM ".tablename('ewei_shop_order_peerpay')." AS p JOIN ".tablename('ewei_shop_order')." AS o ON p.orderid = o.id WHERE p.orderid = :orderid AND p.uniacid = :uniacid AND (p.status = 0 OR p.status=1) AND o.status >= 0 LIMIT 1";
        $query = pdo_fetch($sql,array(':orderid'=>$orderid,':uniacid'=>$_W['uniacid']));
        return $query;
    }

    public function peerStatus($param){
        global $_W;
        if (!empty($param['tid'])){
            $sql = "SELECT id FROM ".tablename('ewei_shop_order_peerpay_payinfo')." WHERE tid = :tid";
            $id = pdo_fetchcolumn($sql,array(':tid'=>$param['tid']));
            if ($id) return $id;
        }
        return pdo_insert('ewei_shop_order_peerpay_payinfo',$param);
    }

    //查询订单记次时商品是否可以领取核销卡
    public function getVerifyCardNumByOrderid($orderid){
        global $_W;
        $num = pdo_fetchcolumn('select SUM(og.total)  from ' . tablename('ewei_shop_order_goods') . ' og
		 inner join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id
		 where og.uniacid=:uniacid  and og.orderid =:orderid and g.cardid>0', array(':uniacid' => $_W['uniacid'],':orderid' => $orderid));

        return $num;
    }

    //判断是否是纯记次时商品订单
    public function checkisonlyverifygoods($orderid){
        global $_W;
        $num = pdo_fetchcolumn('select COUNT(1)  from ' . tablename('ewei_shop_order_goods') . ' og
		 inner join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id
		 where og.uniacid=:uniacid  and og.orderid =:orderid and g.type<>5', array(':uniacid' => $_W['uniacid'],':orderid' => $orderid));


        $num = intval($num);
        if($num>0)
        {
            return false;
        }else
        {
            $num2 = pdo_fetchcolumn('select COUNT(1)  from ' . tablename('ewei_shop_order_goods') . ' og
             inner join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id
             where og.uniacid=:uniacid  and og.orderid =:orderid and g.type=5', array(':uniacid' => $_W['uniacid'],':orderid' => $orderid));
            $num2 = intval($num2);

            if($num2>0)
            {
                return true;
            }else
            {
                return false;
            }
        }
    }

    //判断是否包含记次时商品
    public function checkhaveverifygoods($orderid){
        global $_W;
        $num = pdo_fetchcolumn('select COUNT(1)  from ' . tablename('ewei_shop_order_goods') . ' og
		 inner join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id
		 where og.uniacid=:uniacid  and og.orderid =:orderid and g.type=5', array(':uniacid' => $_W['uniacid'],':orderid' => $orderid));

        $num = intval($num);
        if($num>0)
        {
            return true;
        }else{
            return false;
        }
    }

    //判断订单是否包含存在核销记录的记次时商品
    public function checkhaveverifygoodlog($orderid){
        global $_W;
        $num = pdo_fetchcolumn('select COUNT(1)  from ' . tablename('ewei_shop_verifygoods_log') . ' vl
		 inner join ' . tablename('ewei_shop_verifygoods') . ' v on vl.verifygoodsid = v.id
		 where v.uniacid=:uniacid  and v.orderid =:orderid ', array(':uniacid' => $_W['uniacid'],':orderid' => $orderid));

        $num = intval($num);
        if($num>0)
        {
            return true;
        }else{
            return false;
        }
    }

    public function countOrdersn($ordersn, $str = "TR"){
        global $_W;

        $count = intval(substr_count($ordersn, $str));
        return $count;
    }

    /**
     * 获取订单的虚拟卡密信息
     * @param array $order
     * @return bool
     */
    public function getOrderVirtual($order=array()){
        global $_W;

        if(empty($order)){
            return false;
        }

        if(empty($order['virtual_info'])){
            return $order['virtual_str'];
        }

        $ordervirtual = array();
        $virtual_type = pdo_fetch('select fields from ' . tablename('ewei_shop_virtual_type') . ' where id=:id and uniacid=:uniacid and merchid = :merchid limit 1 ', array(':id' => $order['virtual'], ':uniacid' => $_W['uniacid'], ':merchid' => $order['merchid']));
        if(!empty($virtual_type)){
            $virtual_type = iunserializer($virtual_type['fields']);
            $virtual_info = ltrim($order['virtual_info'], '[');
            $virtual_info = rtrim($virtual_info, ']');
            $virtual_info = explode(',', $virtual_info);

            if(!empty($virtual_info)){
                foreach ($virtual_info as $index=>$virtualinfo){
                    $virtual_temp = iunserializer($virtualinfo);
                    if(!empty($virtual_temp)){
                        foreach ($virtual_temp as $k=>$v){
                            $ordervirtual[$index][] = array(
                                'key'=>$virtual_type[$k],
                                'value'=>$v,
                                'field'=>$k
                            );
                        }
                        unset($k,$v);
                    }
                }
                unset($index, $virtualinfo);
            }
        }

        return $ordervirtual;
    }

    //////////////////////////////////////达达接口相关

    /**
     * 签名生成signature
     */
    public function dada_sign($data,$app_secret){
        //1.升序排序
        ksort($data);
        //2.字符串拼接
        $args = "";
        foreach ($data as $key => $value) {
            $args.=$key.$value;
        }
        $args = $app_secret.$args.$app_secret;//达达开发者app_secret
        //3.MD5签名,转为大写
        $sign = strtoupper(md5($args));
        return $sign;
    }


    /**
     * 构造请求数据
     * data:业务参数，json字符串
     */
    public function dada_bulidRequestParams($data,$app_key,$source_id,$app_secret){
        $requestParams = array();
        $requestParams['app_key'] = $app_key;//达达开发者app_key
        $requestParams['source_id'] = $source_id;//商户ID
        $requestParams['body'] = json_encode($data);
        $requestParams['format'] = 'json';
        $requestParams['v'] = '1.0';
        $requestParams['timestamp'] = time();
        $requestParams['signature'] = $this->dada_sign($requestParams,$app_secret);
        return $requestParams;
    }
    /**
     * 获取达达的配送城市信息
     */
    public function getdadacity(){
        //测试接口地址
//        $url = 'http://newopen.qa.imdada.cn/api/cityCode/list';
        //正式接口地址
        $url = 'http://newopen.imdada.cn/api/cityCode/list';
        $app_key = 'dadace1e05194d2085f';
//      $source_id= '73753' 测试的;
        $source_id= '73753';
        $app_secret = '6cac5477cbfc0ae1ccfa8ceaa7707d85';
        $reqParams=$this->dada_bulidRequestParams('',$app_key,$source_id,$app_secret);
        load()->func('communication');
        $resp =ihttp_request($url, json_encode($reqParams), array('Content-Type' => 'application/json'));
        $ret = @json_decode($resp['content'], true);
       return $ret;
    }


    public  function dada_send($order){
        global $_W;

        //测试接口地址
//        $url = 'http://newopen.qa.imdada.cn/api/order/addOrder';
        //正式接口地址
        $url = 'http://newopen.imdada.cn/api/order/addOrder';

        $cityexpress = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_city_express') . " WHERE uniacid=:uniacid AND merchid=:merchid",array(":uniacid"=>$_W['uniacid'],":merchid"=>0));
        if (!empty($cityexpress)) {
            $config=unserialize($cityexpress['config']);

            //如果是达达
            if($cityexpress['express_type']==1){
                $app_key=$config['app_key'];
                $app_secret=$config['app_secret'];
                $source_id=$config['source_id'];
                $shop_no=$config['shop_no'];
                $city_code=$config['city_code'];
                $receiver=unserialize($order['address']);

                $location_data=m('util')->geocode($receiver['province'].$receiver['city'].$receiver['area'].$receiver['address'],$cityexpress['geo_key']);
                if($location_data['status']==1 && $location_data['count']>0){
                    $location=explode(',',$location_data['geocodes'][0]['location']);

                    $data = array(
                        'shop_no' => $shop_no,//门店编号，门店创建后可在门店列表和单页查看
                        'city_code' =>$city_code,//订单所在城市的code

                        'origin_id' => $order['ordersn'],//	第三方订单ID
                        'info' => $order['remark'],//订单备注
                        'cargo_price' => $order['price'],//订单金额
                        'receiver_name' => $receiver['realname'],//收货人姓名
                        'receiver_address' =>$receiver['province'].$receiver['city'].$receiver['area'].$receiver['address'],//收货人地址
                        'receiver_phone' => $receiver['mobile'],//收货人手机号（手机号和座机号必填一项）
                        'receiver_lng' => $location[0],//收货人地址经度（高德坐标系）
                        'receiver_lat' => $location[1],//收货人地址维度（高德坐标系）

                        'is_prepay' => 0,//是否需要垫付 1:是 0:否 (垫付订单金额，非运费)
                        'expected_fetch_time' => time()+600,//期望取货时间
                        'callback' => 'http://newopen.imdada.cn/inner/api/order/status/notify'
                    );

                    $reqParams=$this->dada_bulidRequestParams($data,$app_key,$source_id,$app_secret);

                    load()->func('communication');
                    $resp =ihttp_request($url, json_encode($reqParams), array('Content-Type' => 'application/json'));
                    $ret = @json_decode($resp['content'], true);
                    if($ret['code']==0){
                        return array('state'=>1,'result'=>'发货成功');
                    }else{
                        return array('state'=>0,'result'=>$ret['msg']);
                    }

                }else{
                    //地理逆解析出错，不支持同城配送
                    return array('state'=>0,'result'=>'获取收件人坐标失败，请检查收件人地址');
                }
            }else{
                //商家自行配送
                return array('state'=>1,'result'=>'发货成功');
            }
        }
    }

    /**
     * //检测产品的库存
     * @param type $orderid
     * @param type $type 0 下单 1 支付
     */
    function CheckoodsStock($orderid = '', $type = 0)
    {
        global $_W;

        $order = pdo_fetch('select id,ordersn,price,openid,dispatchtype,addressid,carrier,status,isparent,paytype,isnewstore,storeid,istrade,status from ' . tablename('ewei_shop_order') . ' where id=:id limit 1', array(':id' => $orderid));

        if (!empty($order['istrade'])) {
            return false;
        }

        if (empty($order['isnewstore'])) {
            $newstoreid = 0;
        } else {
            $newstoreid = intval($order['storeid']);
        }

        $param = array();
        $param[':uniacid'] = $_W['uniacid'];

        if ($order['isparent'] == 1) {
            $condition = " og.parentorderid=:parentorderid";
            $param[':parentorderid'] = $orderid;
        } else {
            $condition = " og.orderid=:orderid";
            $param[':orderid'] = $orderid;
        }

        $goods = pdo_fetchall("select og.goodsid,og.total,g.totalcnf,og.realprice,g.credit,og.optionid,g.total as goodstotal,og.optionid,g.sales,g.salesreal,g.type from " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
            . " where $condition and og.uniacid=:uniacid ", $param);

        if(!empty($goods)) {
            foreach ($goods as $g) {
                if($newstoreid > 0) {
                    $store_goods = m('store')->getStoreGoodsInfo($g['goodsid'], $newstoreid);
                    if(empty($store_goods)) {
                        return;
                    }
                    $g['goodstotal'] = $store_goods['stotal'];
                } else {
                    $goods_item = pdo_fetch("select total as goodstotal from" . tablename('ewei_shop_goods') . " where id=:id and uniacid=:uniacid limit 1", array(":id" => $g['goodsid'], ':uniacid' => $_W['uniacid']));
                    $g['goodstotal'] = $goods_item['goodstotal'];
                }

                $stocktype = 0; //0 不设置库存情况 -1 减少 1 增加
                if($type == 0) {
                    //如果是下单
                    if($g['totalcnf'] == 0) {
                        //少库存
                        $stocktype = -1;
                    }
                } else if($type == 1) {
                    if($g['totalcnf'] == 1) {
                        //少库存
                        $stocktype = -1;
                    }
                }
                if(!empty($stocktype)) {
                    $data = m('common')->getSysset('trade');
                    if(!empty($data['stockwarn'])) {
                        $stockwarn = intval($data['stockwarn']);
                    } else {
                        $stockwarn = 5;
                    }

                    if(!empty($g['optionid'])) {
                        //减少规格库存
                        $option = m('goods')->getOption($g['goodsid'], $g['optionid']);

                        if($newstoreid > 0) {
                            $store_goods_option = m('store')->getOneStoreGoodsOption($g['optionid'], $g['goodsid'], $newstoreid);

                            if(empty($store_goods_option)) {
                                return;
                            }
                            $option['stock'] = $store_goods_option['stock'];
                        }


                        if(!empty($option) && $option['stock'] != -1) {
                            if($stocktype == -1 && $type == 0) {
                                //                                radis判断并发
                                $open_redis = function_exists('redis') && !is_error(redis());
                                if($open_redis){
                                    $redis_key = "{$_W['uniacid']}_goods_order_option_stock_{$option['id']}";
                                    $redis = redis();
                                    //判断是否有这个产品对应的记录
                                    if ($redis->setnx($redis_key, $option['stock'])) {
                                        $totalstock = $redis->get($redis_key);
                                        $newstock = $totalstock-$g['total'];
                                        //判断当前产品购买之后的库存
                                        if($newstock<0){
                                            $redis->delete($redis_key);
                                            return false;
                                        }else{
//                                        更新库存
                                            $redis->set($redis_key,$newstock);
                                        }
                                    } else {
                                        //直接获取产品库存
                                        $totalstock = $redis->get($redis_key);
                                        $newstock = $totalstock-$g['total'];
                                        if($newstock<0){
                                            $redis->delete($redis_key);
                                            return false;
                                        }else{
//                                        更新
                                            $redis->set($redis_key,$newstock);
                                        }
                                    }
                                }else{
                                    return true;
                                }
                            } else if($stocktype == -1 && $type == 1) {
//                                radis判断并发
                            $open_redis = function_exists('redis') && !is_error(redis());
                            if($open_redis){
                                $redis_key = "{$_W['uniacid']}_goods_order_option_stock_{$option['id']}";
                                $redis = redis();
                                //判断是否有这个产品对应的记录
                                if ($redis->setnx($redis_key, $option['stock'])) {
                                    $totalstock = $redis->get($redis_key);
                                    $newstock = $totalstock-$g['total'];
                                    //判断当前产品购买之后的库存
                                    if($newstock<0){
                                        $redis->delete($redis_key);
                                        return false;
                                    }else{
//                                        更新库存
                                        $redis->set($redis_key,$newstock);
                                    }
                                } else {
                                    //直接获取产品库存
                                    $totalstock = $redis->get($redis_key);
                                    $newstock = $totalstock-$g['total'];
                                    if($newstock<0){
                                        $redis->delete($redis_key);
                                        return false;
                                    }else{
//                                        更新
                                        $redis->set($redis_key,$newstock);
                                    }
                                }
                            }else{
                                return true;
                            }
                            }
                        }
                    }
                    if(!empty($g['goodstotal']) && $g['goodstotal'] != -1) {
                        //减少商品总库存
                        if($stocktype == -1 && $type == 0) {
                            //                            radis判断并发
                            $open_redis = function_exists('redis') && !is_error(redis());
                            if($open_redis){
                                $redis_key = "{$_W['uniacid']}_goods_order_stock_{$g['goodsid']}";
                                $redis = redis();
                                //判断是否有这个产品对应的记录
                                if ($redis->setnx($redis_key, $g['goodstotal'])) {
                                    $totalstock = $redis->get($redis_key);
                                    $newstock = $totalstock-$g['total'];
                                    //判断当前产品购买之后的库存
                                    if($newstock<0){
                                        $redis->delete($redis_key);
                                        return false;
                                    }else{
//                                        更新库存
                                        $redis->set($redis_key,$newstock);
                                    }
                                } else {
                                    //直接获取产品库存
                                    $totalstock = $redis->get($redis_key);
                                    $newstock = $totalstock-$g['total'];
                                    if($newstock<0){
                                        $redis->delete($redis_key);
                                        return false;
                                    }else{
//                                        更新
                                        $redis->set($redis_key,$newstock);
                                    }
                                }
                            }else{
                                return true;
                            }
                        } else if($stocktype == -1 && $type == 1) {
//                            radis判断并发
                            $open_redis = function_exists('redis') && !is_error(redis());
                            if($open_redis){
                                $redis_key = "{$_W['uniacid']}_goods_order_stock_{$g['goodsid']}";
                                $redis = redis();
                                //判断是否有这个产品对应的记录
                                if ($redis->setnx($redis_key, $g['goodstotal'])) {
                                    $totalstock = $redis->get($redis_key);
                                    $newstock = $totalstock-$g['total'];
                                    //判断当前产品购买之后的库存
                                    if($newstock<0){
                                        $redis->delete($redis_key);
                                        return false;
                                    }else{
//                                        更新库存
                                        $redis->set($redis_key,$newstock);
                                    }
                                } else {
                                    //直接获取产品库存
                                    $totalstock = $redis->get($redis_key);
                                    $newstock = $totalstock-$g['total'];
                                    if($newstock<0){
                                        $redis->delete($redis_key);
                                        return false;
                                    }else{
//                                        更新
                                        $redis->set($redis_key,$newstock);
                                    }
                                }
                            }else{
                                return true;
                            }

                        }
                    } else if($g['goodstotal'] == 0) {
                        $totalstock = 0;
                        $totalstock = $g['goodstotal'] - $g['total'];
                        if($totalstock < 0) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }else{
            return false;
        }
    }

}