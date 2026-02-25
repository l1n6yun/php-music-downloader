# PHP Music Downloader

A command-line tool to download music from the internet.

[中文版本](README_zh.md) | [English Version](README.md)

## Features
- Download songs by singer name
- Automatic music directory creation
- Progress bar for download status
- Error handling for robust operation

## Requirements
- PHP 7.4+
- Composer

## Dependencies
- [Symfony Console](https://symfony.com/doc/current/components/console.html) - For command-line interface
- [Guzzle HTTP Client](https://docs.guzzlephp.org/en/stable/) - For HTTP requests
- [QueryList](https://querylist.cc/) - For HTML parsing

## Installation

1. Clone the repository
2. Install dependencies:

```bash
composer install
```

## Usage

Run the download command with the singer name as argument:

```bash
php download.php <singer_name>
```

Example:

```bash
php download.php Taylor Swift
```

## How It Works

1. **Search**: Searches for songs by the specified singer on gequbao.com
2. **Extract**: Extracts song information from the search results
3. **Fetch Details**: Gets detailed information for each song
4. **Get Play URL**: Retrieves the actual playback URL for each song
5. **Download**: Downloads the songs to the `music` directory

## Output

Songs will be downloaded to the `music` directory in the project root, with filenames in the format `{song_title}-{artist}.mp3`.

## License

MIT