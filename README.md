# Barrett-captcha
验证码扩展库

## 使用

### 一、安装
> composer require barrett/captcha

### 二、初始化配置
引入本扩展库后，在实例化时可以传递配置参数，用于处理不同的场景。
```php
$Captcha = new barrett\Captcha(['setZh'=>true,'length'=>4]);
```

> 配置表如下

|参数名|参数类型|默认值|说明|
| ------------ | ------------ | ------------ | ------------ |
|key|string|Barrett|验证码加密key|
|destroy|Boolean|true|验证成功后是否销毁|
|expire|integer|1800|验证码过期时间（s）|
|length|integer|5|验证码长度|
|setZh|Boolean|false|使用中文验证码|
|width|integer|180|图片验证码默认宽度|
|height|integer|50|图片验证码默认高度|
|fontSize|integer|20|验证码字体大小(px)|
|fontTtf|string |   |验证码字体，不设置随机获取|
|useImgBg|Boolean|false|使用背景图片|
|useCurve|Boolean|true|是否画混淆曲线|
|useNoise|Boolean|true|是否添加杂点|
|bg|array|[243, 251, 254]|背景颜色|

### 二、创建验证码
本扩展目前支持：图形验证码、数字验证码（短信用）两种，使用方法如下：
> 创建图形验证码
```php
$Captcha = new barrett\Captcha();
return $Captcha->createImg('user');
```

> 创建短信验证码
```php
$Captcha = new barrett\Captcha();
//createNum方法会返回被创建的验证码
$code = $Captcha->createNum('user');
//使用短信发送数字验证码
$Sms->sendToPhone($code['data]);
```

> 验证码效验
```php
$Captcha = new barrett\Captcha();
return $Captcha->check($_POST,'user');
```

####如输出结果异常请检查页面头部信息是否正常！如：Content-type:image/png 是否设置

### 三、场景标识
用例：客户更改已绑定的手机号，需要在同一个页面生成两个验证码（原手机验证码、新手机验证码）。
1.原手机创建验证码加入唯一场景标识：
```php
$old = $Captcha->createNum('old');
```
2.新手机创建验证码同样加入唯一场景标识：
```php
$new = $Captcha->createNum('new');
```
3.当用户提交数据时则根据唯一场景标识来检测对应场景的验证码是否正确，而不会出现相同页面多次调用后不能效验的问题。