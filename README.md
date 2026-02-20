# 简介
Mediawiki的自动化IndexNow提交插件，使用 MIT 协议。

# 警告
**本插件由 OpenAI GPT-5 模型生成，不保证安全性与正确性。**

为了解决Mediawiki没有人做IndexNow自动化提交插件的问题，我用Chatgpt生成了一个，自己用着似乎还正常，故发出来看看。

# 配置
1. 使用MediaWiki:1.45.1 PHP:8.2.28 ，其余版本未经测试，请谨慎使用。
2. 确保可以通过访问 https://host_name/任意字符串A.txt ，且返回内容为 任意字符串A 。
   
   例如，在你的站点下放置一个 任意字符串A.txt ，且内容为 任意字符串A 。
3. 将本插件下载下来放入 `/extensions` 中，例如 `/extensions/IndexNowNotifier` 。
4. 设置 `LocalSettings.php` ，加入
```
wfLoadExtension( 'IndexNowNotifier' );
$wgIndexNowKey = "任意字符串A（无需.txt）";
```
5. 编辑一个网页，前往 https://www.bing.com/webmasters/indexnow 看看提交的URL数字会不会发生变动。
   
   常规情况下，这个数字是实时的，几乎没有延迟。若出现延迟，请校验上述步骤是否有错误。
