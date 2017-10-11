<?php
// +----------------------------------------------------------------------
// | PHP验证码扩展
// +----------------------------------------------------------------------
// | Fork     : think-captcha
// +----------------------------------------------------------------------
// | Author   : Barrett-M82A1 <i@yoyoyo.me>
// +----------------------------------------------------------------------
// | Licensed : ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------

namespace barrett;

class Captcha
{
    
    protected $config = [

        // 验证码加密KEY
        'key'  => 'Barrett',

        // 验证成功后是否销毁
        'destroy' => true,

        // 验证码过期时间（s）
        'expire'   => 1800,

        // 验证码长度
        'length'   => 5,

        // 使用中文验证码
        'setZh'    => false,

        // 验证码图片宽度
        'width'   => 180,

        // 验证码图片高度
        'height'   => 50,

        // 验证码字体大小(px)
        'fontSize' => 20,

        // 验证码字体，不设置随机获取
        'fontTtf'  => '',

        // 使用背景图片
        'useImgBg' => false,
        
        // 是否画混淆曲线
        'useCurve' => true,
        
        // 是否添加杂点
        'useNoise' => true,
        
        // 背景颜色
        'bg'       => [243, 251, 254],

    ];

    private $_image = null; // 验证码图片实例

    private $_color = null; // 验证码字体颜色

    /**
     * 初始化配置信息
     * @param array $config 配置参数
     */
    public function __construct($config = [])
    {
        // 启动SESSION
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }

        // 设置配置信息
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 使用 $this->name 获取配置
     * @param  string $name 配置名称
     * @return mixed    配置值
     */
    public function __get($name)
    {
        return $this->config[$name];
    }

    /**
     * 设置验证码配置
     * @param  string $name  配置名称
     * @param  string $value 配置值
     */
    public function __set($name, $value)
    {
        if (isset($this->config[$name])) {
            $this->config[$name] = $value;
        }
    }

    /**
     * 检查配置
     * @param  string $name 配置名称
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->config[$name]);
    }

    /**
     * 创建数字验证码
     * @access public
     * @param string $scene 场景唯一标识
     */
    public function createNum($scene = '')
    {
        // 创建验证码
        $code = self::random($this->config['length'],1);

        // 根据场景与code加密
        $name = $this->_encrypt($code,$scene);

        // 保存验证码创建时间
        $_SESSION[$name] = time();

        if (empty($_SESSION[$name])) {
            return ['status'=>false,'msg'=>'创建验证码失败','data'=>''];
        }

        return ['status'=>true,'msg'=>'创建成功','data'=>$code];
    }

    /**
     * 创建图形验证码
     * @access public
     * @param string $scene 场景唯一标识
     */
    public function createImg($scene){
        // 图片宽(px)
        $this->width || $this->width = $this->length * $this->fontSize * 1.5 + $this->length * $this->fontSize / 2;
        // 图片高(px)
        $this->height || $this->height = $this->fontSize * 2.5;
        // 建立一幅 $this->width x $this->height 的图像
        $this->_image = imagecreate($this->width, $this->height);
        // 设置背景
        imagecolorallocate($this->_image, $this->bg[0], $this->bg[1], $this->bg[2]);

        // 验证码字体随机颜色
        $this->_color = imagecolorallocate($this->_image, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));
        // 验证码使用随机字体
        $ttfPath = __DIR__ . '/../assets/fonts/' . ($this->setZh ? 'zh' : 'en') . '/';

        if (empty($this->fontTtf)) {
            $dir  = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && substr($file, -4) == '.ttf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
            $this->fontTtf = $ttfs[array_rand($ttfs)];
        }
        $this->fontTtf = $ttfPath . $this->fontTtf;

        if ($this->useImgBg) {
            $this->_background();
        }

        if ($this->useNoise) {
            // 绘杂点
            $this->_writeNoise();
        }
        if ($this->useCurve) {
            // 绘干扰线
            $this->_writeCurve();
        }

        // 绘验证码
        $code   = []; // 验证码
        $codeNX = 0; // 验证码第N个字符的左边距

        if ($this->setZh) {

            // 创建随机字符
            $random = self::random($this->length,4);

            // 中文验证码
            for ($i = 0; $i < $this->length; $i++) {
                $code[$i] = iconv_substr($random, floor(mt_rand(0, mb_strlen($random, 'utf-8') - 1)), 1, 'utf-8');
                imagettftext($this->_image, $this->fontSize, mt_rand(-40, 40), $this->fontSize * ($i + 1) * 1.5, $this->fontSize + mt_rand(10, 20), $this->_color, $this->fontTtf, $code[$i]);
            }
        } else {

            // 创建随机字符
            $random = self::random($this->length,1);

            for ($i = 0; $i < $this->length; $i++) {
                $code[$i] = $random[mt_rand(0, strlen($random) - 1)];
                $codeNX += mt_rand($this->fontSize * 1.2, $this->fontSize * 1.6);
                imagettftext($this->_image, $this->fontSize, mt_rand(-40, 40), $codeNX, $this->fontSize * 1.6, $this->_color, $this->fontTtf, $code[$i]);
            }
        }

        $code = implode('',$code);

        // 根据场景与code加密
        $name = $this->_encrypt($code,$scene);

        // 保存验证码创建时间
        $_SESSION[$name] = time();

        if (empty($_SESSION[$name])) {
            return ['status'=>false,'msg'=>'创建验证码失败','data'=>''];
        }

        ob_start();
        // 输出图像
        imagepng($this->_image);
        $content = ob_get_clean();
        imagedestroy($this->_image);

        return $content;
    }

    /**
     * 验证验证码是否正确
     * @param string $code  用户验证码
     * @param string $scene 场景唯一标识
     * @return bool 用户验证码是否正确
     */
    public function check($code, $scene = '')
    {
        // 空值检测
        if (empty($code)) {
            return ['status'=>false,'msg'=>'验证码不能为空','data'=>''];
        }

        // SESSION是否存在
        $name = $this->_encrypt($code,$scene);

        if (!isset($_SESSION[$name])) {
            return ['status'=>false,'msg'=>'验证码错误','data'=>''];
        }

        // 检测是否过期
        if (time() - $_SESSION[$name] > $this->expire) {
            // 销毁验证码
            unset($_SESSION[$name]);
            return ['status'=>false,'msg'=>'验证码已过期','data'=>''];
        }

        // 检测是否需要销毁验证码
        if($this->config['destroy']){
            unset($_SESSION[$name]);
        }

        return ['status'=>true,'msg'=>'验证通过','data'=>''];
    }

    /**
     * 获取指定长度的随机字母数字组合的字符串
     * @param string $len 长度
     * @param string $type 字串类型：1大小写字母 2数字 3小写 4汉字 默认混合
     * @param string $chars 额外字符
     */
    public static function random($length = 6, $type = '', $string = '')
    {
        $str = '';
        switch ($type) {
            case 0:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' . $string;
                break;
            case 1:
                $chars = str_repeat('0123456789', 3);
                break;
            case 2:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . $string;
                break;
            case 3:
                $chars = 'abcdefghijklmnopqrstuvwxyz' . $string;
                break;
            case 4:
                $chars = "们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借" . $string;
                break;
            default :
                // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用string参数
                $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789' . $string;
                break;
        }

        // 位数过长重复字符串一定次数
        if ($length > 10) {
            $chars = $type == 1 ? str_repeat($chars, $length) : str_repeat($chars, 5);
        }

        if ($type != 4) {
            $chars = str_shuffle($chars);
            $str = substr($chars, 0, $length);
        } else {
            // 中文随机字
            for ($i = 0; $i < $length; $i++) {
                $str .= iconv_substr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)),1, 'utf-8');
            }
        }
        return $str;
    }


    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     */
    private function _writeCurve()
    {
        $px = $py = 0;

        // 曲线前部分
        $A = mt_rand(1, $this->height / 2); // 振幅
        $b = mt_rand(-$this->height / 4, $this->height / 4); // Y轴方向偏移量
        $f = mt_rand(-$this->height / 4, $this->height / 4); // X轴方向偏移量
        $T = mt_rand($this->height, $this->width * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand($this->width / 2, $this->width * 0.8); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->height / 2; // y = Asin(ωx+φ) + b
                $i  = (int)($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->_image, $px + $i, $py + $i, $this->_color); // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A   = mt_rand(1, $this->height / 2); // 振幅
        $f   = mt_rand(-$this->height / 4, $this->height / 4); // X轴方向偏移量
        $T   = mt_rand($this->height, $this->width * 2); // 周期
        $w   = (2 * M_PI) / $T;
        $b   = $py - $A * sin($w * $px + $f) - $this->height / 2;
        $px1 = $px2;
        $px2 = $this->width;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->height / 2; // y = Asin(ωx+φ) + b
                $i  = (int)($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->_image, $px + $i, $py + $i, $this->_color);
                    $i--;
                }
            }
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    private function _writeNoise()
    {
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($this->_image, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($this->_image, 5, mt_rand(-10, $this->width), mt_rand(-10, $this->height), $codeSet[mt_rand(0, 29)], $noiseColor);
            }
        }
    }

    /**
     * 绘制背景图片
     * 注：如果验证码输出图片比较大，将占用比较多的系统资源
     */
    private function _background()
    {
        $path = __DIR__ . '/../assets/backgrounds/';
        $dir  = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

        $gb = $bgs[array_rand($bgs)];

        list($width, $height) = @getimagesize($gb);
        // Resample
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled($this->_image, $bgImage, 0, 0, 0, 0, $this->width, $this->height, $width, $height);
        @imagedestroy($bgImage);
    }

    /**
     * 加密验证码
     * @param string $code  用户验证码
     * @param string $scene 场景唯一标识
     * @return bool 用户验证码是否正确
     */
    private function _encrypt($code,$scene){
        $code = substr(md5($code), 8, 10);
        return md5($this->key.$code.$scene);
    }
}