# PHP 音乐下载器

一个命令行工具，用于从互联网下载音乐。

[中文版本](README_zh.md) | [English Version](README.md)

## 功能
- 通过歌手名称下载歌曲
- 自动创建音乐目录
- 下载状态进度条
- 错误处理，确保稳定运行

## 要求
- PHP 7.4+
- Composer

## 依赖
- [Symfony Console](https://symfony.com/doc/current/components/console.html) - 用于命令行界面
- [Guzzle HTTP Client](https://docs.guzzlephp.org/en/stable/) - 用于 HTTP 请求
- [QueryList](https://querylist.cc/) - 用于 HTML 解析

## 安装

1. 克隆仓库
2. 安装依赖：

```bash
composer install
```

## 使用方法

运行下载命令，指定歌手名称作为参数：

```bash
php download.php <歌手名称>
```

示例：

```bash
php download.php 周杰伦
```

## 工作原理

1. **搜索**：在 gequbao.com 上搜索指定歌手的歌曲
2. **提取**：从搜索结果中提取歌曲信息
3. **获取详情**：获取每首歌曲的详细信息
4. **获取播放 URL**：检索每首歌曲的实际播放 URL
5. **下载**：将歌曲下载到 `music` 目录

## 输出

歌曲将被下载到项目根目录的 `music` 文件夹中，文件名为 `{歌曲标题}-{艺术家}.mp3` 格式。

## 许可证

MIT