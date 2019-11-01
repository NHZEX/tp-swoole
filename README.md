# Think Swoole
用于 Thinkphp6.0 的 Swoole 扩展

## 环境需求
- php >= 7.2
- swoole >= 4.4.6

## 安装
```
composer require nhzex/tp-swoole
```

## 启动
```shell script
php think server
```

## 配置文件
- 定义插件：\HZEX\TpSwoole\Contract\InteractsWithWorker
- 定义事件监听：\unzxin\zswCore\Contract\EventSubscribeInterface
- 定义进程：\unzxin\zswCore\Process\BaseSubProcess
- 定义任务实现：\HZEX\TpSwoole\Task\TaskInterface

## 代码引用
- [think-swoole](https://github.com/top-think/think-swoole)