<?php
/**
 * Created by PhpStorm.
 * User: cjf
 * Date: 2018/7/12
 * Time: 下午7:28
 */
class LotteryApplyListExtends extends  LotteryApplyList{


    private $_common = '';
    private $_cache_prefix = 'LotteryApplyListExtends:';
    private $_model  = 'LotteryApplyList';

    public  $cache_switch = true; // 缓存开关
    public  $err_msg  = '';
    public  $err_code = '';
    public  $MC_KEYS_LIMIT = 500;
    public  $pk = 'id';

    public function init() {
        parent::init();
        $this->_common = new Common();

    }

    /**
     * 添加一条内容
     *
     * @param array $row 添加的记录数组
     *
     * @return int
     */
    public function add($row) {

        try
        {
            $m  = new $this->_model();
            $m->attributes = $row;
            if($m->save()) {
                $key = md5($this->_cache_prefix . 'pk:' . $m->attributes[$this->pk]);
                if($this->cache_switch) $this->_common->setYiiCache($key,json_encode($row),600);
            }
        }
        catch (Exception $e)
        {
            $this->err_code = 10002;
            $this->err_msg  = $e->getMessage();
        }
        if($m->attributes[$this->pk]) return $m->attributes[$this->pk];
    }

    /**
     * 删除一条内容
     *
     * @param int $pk 删除的pk
     *
     * @return int
     */
    public function delByPk($pk) {

        if(!$pk)
        {
            $this->err_code = 10001;
            $this->err_msg  = '缺少参数';
            return;
        }

        try
        {
            $key = md5($this->_cache_prefix . 'pk:' . $pk);
            $this->_common->setYiiCache($key,'',1);
            $m   = new $this->_model();
            $res = $m->deleteByPk($pk);
        }
        catch (Exception $e)
        {
            $this->err_code = 10003;
            $this->err_msg  = $e->getMessage();
        }
        return isset($res) && $res ? $res : false;
    }

    /**
     * 修改一条内容
     *
     * @param int $pk 内容pk
     * @param array $row 要修改的数组
     *
     * @return int
     */
    public function change($pk,$row) {

        if(!$pk or !$row)
        {
            $this->err_code = 10001;
            $this->err_msg  = '缺少参数';
            return;
        }

        try
        {
            $key = md5($this->_cache_prefix . 'pk:' . $pk);
            $m   = new $this->_model();
            $res = $m->updateByPk($pk,$row);
            if($res) $this->_common->setYiiCache($key,json_encode($row),600);
        }
        catch (Exception $e)
        {
            $this->err_code = 10004;
            $this->err_msg  = $e->getMessage();
        }
        return isset($res) && $res ? $res : false;
    }

    /**
     * 获得一条内容
     *
     * @param int $pk 获得的pk
     *
     * @return array
     */
    public function getInfoByPk($pk) {

        $key = md5($this->_cache_prefix . 'pk:' . $pk);
        if($this->cache_switch && $json_info = $this->_common->getYiiCache($key)) $info = json_decode($json_info,1);
        if(!$info or !$info[$this->pk]) {
            $m    = new $this->_model();
            $tmp_info = $m->findByPk($pk);
            if($tmp_info) $info = $this->_common->evalJson($tmp_info);
            if($info) $this->_common->setYiiCache($key,json_encode($info),600);
        }
        return $info;
    }

    /**
     * 获得列表内容
     *
     * @param array $pkAry 获得的pk数组
     * @return array
     */
    public function getListByPkAry($pkAry) {
        if(!$pkAry) return array();
        $ret = $res = array();
        if($this->cache_switch) {
            if(count($pkAry) > $this->MC_KEYS_LIMIT) {
                $pkAryAry = array_chunk($pkAry,$this->MC_KEYS_LIMIT);
                foreach($pkAryAry as $_pkAry) {
                    $ret += $this->_getListByPkAry($_pkAry);
                }
            } else {
                $ret = $this->_getListByPkAry($pkAry);
            }
        } else {
            $ret = $this->getListRealByPkAry($pkAry);
        }
        foreach ($pkAry as $pk) {
            $res[] = $ret[$pk];
        }
        return $res;
    }

    /**
     * 获得pk数组
     *
     * @param string $where 条件
     * @param string $start 开始位置
     * @param string $limit 搜索条数
     * @param string $order 排序
     * @return array
     */
    public function getPkAryByWhere($where='',$start=0,$limit='',$order='') {

        $key = $this->_cache_prefix . "where:{$where}:order:{$order}:$start:{$start}:limit:{$limit}";
        //添加where条件循环体
        if($this->cache_switch && false !== ($pkStr = $this->_common->getYiiCache($key))) {
            $pkAry = explode(',',$pkStr);
        } else {
            $pkAry = array();
            $m     = new $this->_model();
            $cdbcriteria = new CDbCriteria();
            $cdbcriteria->offset = $start;
            $cdbcriteria->select = $this->pk;
            if($where) $cdbcriteria->addCondition($where);
            if($order) $cdbcriteria->order = $order;
            if($limit) $cdbcriteria->limit = $limit;
            $tmp_pkAry = $m->findAll($cdbcriteria);
            $tmp_pkAry = $this->_common->evalJson($tmp_pkAry);
            if($tmp_pkAry)
            {
                foreach ($tmp_pkAry as $v)
                {
                    $pkAry[] =  $v[$this->pk];
                }
            }
            if ($pkAry) {
                $this->_common->setYiiCache($key, implode(',',$pkAry),$_expire=300);
            } else {
                $this->_common->setYiiCache($key,'',1);
            }
        }
        return $pkAry;
    }

    /**
     * 获得pk数组
     *
     * @param array $where 获得的pk数组
     * @return array
     */
    public function _getListByPkAry($pkAry) {

        //命中
        if($this->cache_switch) {

            $ret = $notHit = array();
            foreach ($pkAry as $v)
            {
                $key  = md5($this->_cache_prefix . 'pk:' . $v);
                $info = $this->_common->getYiiCache($key);
                if(!$info or !$info=json_decode($info,1))
                {
                    $notHit[] = $v;
                    continue;
                }
                $ret[$v] = $info;
            }
            //未命中部分
            if($notHit) $ret += $this->getListRealByPkAry($notHit);
        } else {
            //全部未命中！直接调用 real 版本得到结果
            $ret = $this->getListRealByPkAry($pkAry);
        }

        //保证按序返回
        $return = array();
        foreach($pkAry as $pk) {
            if(isset($ret[$pk])) $return[$pk] = $ret[$pk];
        }
        return $return;
    }

    /**
     * 根据pk获得数组
     *
     * @param array $where 根据pk获得的数组
     * @return array
     */
    public function getListRealByPkAry($pkAry) {

        if(sizeof($pkAry)<1) {
            $this->err_code = 10001;
            $this->err_msg  = '缺少参数';
            return;
        }

        $m     = new $this->_model();
        $cdbcriteria = new CDbCriteria();
        $cdbcriteria->select = '*';
        $cdbcriteria->addCondition($this->pk.' in ('.implode(',',$pkAry).')');
        try{
            $tmp_pkAry = $m->findAll($cdbcriteria);
        }
        catch (Exception $e)
        {
            $this->err_code = 10004;
            $this->err_msg  = $e->getMessage();
        }
        $res = $this->_common->evalJson($tmp_pkAry);
        if($res) {
            foreach ($res as $v) {
                $return[$v[$this->pk]] = $v;
                $key  = md5($this->_cache_prefix . 'pk:' . $v[$this->pk]);
                $this->_common->setYiiCache($key,json_encode($v),600);
            }
        }
        return $return;
    }


    /**
     * 统计
     *
     * @param string $where 条件
     * @param string $start 开始位置
     * @param string $limit 搜索条数
     * @param string $order 排序
     * @return array
     */
    public function countByWhere($where='',$start=0,$limit='',$order='') {
        $key = $this->_cache_prefix . "_countByWhere_where:{$where}:order:{$order}:$start:{$start}:limit:{$limit}";
        //添加where条件循环体
        if(!$this->cache_switch or (!$count = $this->_common->getYiiCache($key))) {

            $m     = new $this->_model();
            $cdbcriteria = new CDbCriteria();
            $cdbcriteria->offset = $start;
            if($where) $cdbcriteria->addCondition($where);
            if($order) $cdbcriteria->order = $order;
            if($limit) $cdbcriteria->limit = $limit;
            $count = $m->count($cdbcriteria);
            if ($count) {
                $this->_common->setYiiCache($key, $count,$_expire=300);
            } else {
                $this->_common->setYiiCache($key,'',1);
            }
        }
        return $count ? $count : 0;
    }
}
