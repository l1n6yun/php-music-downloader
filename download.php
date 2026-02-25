<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use QL\QueryList;
use GuzzleHttp\Client;

class DownloadCommand extends Command
{
    protected static $defaultName = 'download';
    protected static $defaultDescription = '下载指定歌手的歌曲';
    private $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ]
        ]);
    }

    protected function configure()
    {
        $this
            ->addArgument('singer', InputArgument::REQUIRED, '歌手名称');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 创建两个独立的输出部分
        $textSection = $output->section();
        $progressSection = $output->section();
        
        $singer = $input->getArgument('singer');
        $textSection->writeln("开始下载 {$singer} 的歌曲...");

        // 创建music目录
        $musicDir = __DIR__ . '/music';
        if (!is_dir($musicDir)) {
            mkdir($musicDir, 0777, true);
            $textSection->writeln("创建music目录: <info>成功</info>");
        }

        // 第一步：搜索歌曲列表
        $searchUrl = "https://www.gequbao.com/s/{$singer}";
        $textSection->write("正在搜索歌曲列表... ");
        
        try {
            $response = $this->client->get($searchUrl);
            $html = $response->getBody()->getContents();
            $ql = QueryList::html($html);
            $textSection->writeln("<info>完成</info>");
            
            // 提取歌曲列表
            $textSection->write("正在提取歌曲列表... ");
            $songs = $ql->find('.row.no-gutters.py-2d5.border-top')->map(function($item) {
                $link = $item->find('a.hover-zoom')->attr('href');
                $title = $item->find('span.text-primary')->text();
                $artist = $item->find('small.text-jade')->text();
                return [
                    'url' => $link,
                    'title' => $title,
                    'artist' => $artist
                ];
            })->values();

            $totalSongs = $songs->count();
            $textSection->writeln("<info>找到 {$totalSongs} 首歌曲</info>");

            // 创建进度条并绑定到独立的输出部分
            $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($progressSection, $totalSongs);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();

            // 遍历歌曲列表
            $index = 0;
            foreach ($songs as $song) {
                $index++;
                $textSection->writeln("\n处理第 " . $index . " 首：{$song['title']} - {$song['artist']}");
                $textSection->writeln("-----------------------------------");
                
                // 第二步：获取歌曲详情
                $detailUrl = "https://www.gequbao.com{$song['url']}";
                $textSection->write("正在获取歌曲详情... ");
                
                try {
                    $response = $this->client->get($detailUrl);
                    $detailHtml = $response->getBody()->getContents();
                    $textSection->writeln("<info>完成</info>");
                } catch (Exception $e) {
                    $textSection->writeln("<error>获取失败，跳过此歌曲</error>");
                    continue;
                }
                
                // 提取window.appData
                $textSection->write("正在解析window.appData... ");
                if (!preg_match('/window\.appData\s*=\s*JSON\.parse\((.*?)\);/', $detailHtml, $matches)) {
                    $textSection->writeln("<error>未找到</error>");
                    continue;
                }
                
                try {
                    $jsonStr = $matches[1];
                    
                    // 处理JSON字符串
                    $jsonStr = html_entity_decode($jsonStr);
                    $jsonStr = trim($jsonStr, "'");
                    $jsonStr = stripslashes($jsonStr);
                    $jsonStr = preg_replace('/u0022/', '"', $jsonStr);
                    
                    $appData = json_decode($jsonStr, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("JSON解析错误: " . json_last_error_msg());
                    }
                    
                    if (!isset($appData['play_id'])) {
                        throw new Exception("未找到play_id");
                    }
                    
                    $playId = $appData['play_id'];
                    $textSection->writeln("<info>完成</info>");
                    
                    // 第三步：获取播放地址
                    $textSection->write("正在获取播放地址... ");
                    $playUrl = $this->getPlayUrl($playId);
                    
                    if (!$playUrl) {
                        throw new Exception("获取播放地址失败");
                    }
                    
                    $textSection->writeln("<info>完成</info>");
                    
                    // 下载歌曲
                    $fileName = "{$song['title']}-{$song['artist']}.mp3";
                    // 清理文件名中的特殊字符
                    $fileName = preg_replace('/[<>:"\\/|?*]/', '_', $fileName);
                    $filePath = $musicDir . '/' . $fileName;
                    
                    $textSection->write("正在下载歌曲到: {$fileName}... ");
                    
                    if (!$this->downloadFile($playUrl, $filePath)) {
                        throw new Exception("下载失败");
                    }
                    
                    $textSection->writeln("<info>成功</info>");
                } catch (Exception $e) {
                    $textSection->writeln("<error>错误: " . $e->getMessage() . "</error>");
                }
                
                // 更新进度条
                $progressBar->advance();
            }
            
            // 完成进度条
            $progressBar->finish();
            $textSection->writeln("\n所有歌曲处理完成！");
            return Command::SUCCESS;
        } catch (Exception $e) {
            $textSection->writeln("错误: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getPlayUrl($playId)
    {
        $url = "https://www.gequbao.com/api/play-url";
        // 创建一个新的客户端实例，使用适合AJAX请求的头
        $ajaxClient = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest'
            ]
        ]);
        
        try {
            $response = $ajaxClient->post($url, [
                'form_params' => [
                    'id' => $playId
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['code']) && $data['code'] == 1 && isset($data['data']['url'])) {
                return $data['data']['url'];
            }
        } catch (Exception $e) {
            // 忽略错误，返回false
        }
        return false;
    }

    private function downloadFile($url, $filePath)
    {
        try {
            // 使用Guzzle从真实的URL下载文件
            $client = new Client([
                'timeout' => 60,
                'verify' => false
            ]);
            $response = $client->get($url);
            file_put_contents($filePath, $response->getBody()->getContents());
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

$command = new DownloadCommand();
// 运行命令
$application = new Application();
$application->add($command);
$application->setDefaultCommand($command->getName(), true);
$application->run();
