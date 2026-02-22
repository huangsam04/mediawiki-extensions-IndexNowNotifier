<?php

namespace IndexNowNotifier;

use MediaWiki\MediaWikiServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;

class Hooks {

    /**
     * 向 IndexNow 提交 URL
     */
    private static function submitToIndexNow( string $url ): bool {
    
        $logger = LoggerFactory::getInstance( 'IndexNowNotifier' );
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $key = $config->get( 'IndexNowKey' );
    
        if ( empty( $key ) ) {
            $logger->warning( 'IndexNowKey not configured. Skipping submission.' );
            return true;
        }
    
        $host = parse_url( $url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            $logger->error( 'Failed to parse host from URL: ' . $url );
            return true;
        }
    
        $payload = json_encode([
            'host' => $host,
            'key' => $key,
            'urlList' => [ $url ]
        ]);
    
        if ( $payload === false ) {
            $logger->error( 'JSON encode failed: ' . json_last_error_msg() );
            return true;
        }
    
        $logger->info(
            'Submitting URL to IndexNow | URL=' . $url .
            ' | HOST=' . $host .
            ' | PAYLOAD=' . $payload
        );
    
        $start = microtime(true);
    
        $ch = curl_init('https://api.indexnow.org/indexnow');
    
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);
    
        $response = curl_exec($ch);
    
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    
        curl_close($ch);
    
        $duration = round(microtime(true) - $start, 3);
    
        if ($response === false) {
            $logger->error(
                'IndexNow cURL failed | URL=' . $url .
                ' | ERRNO=' . $errno .
                ' | ERROR=' . $error .
                ' | DURATION=' . $duration . 's'
            );
            return true;
        }
    
        $logger->info(
            'IndexNow response received | URL=' . $url .
            ' | HTTP=' . $httpCode .
            ' | RESPONSE=' . $response .
            ' | TIME=' . $totalTime .
            ' | DURATION=' . $duration .
            ' | IP=' . $primaryIp
        );
    
        return true;
    }

    /**
     * 页面保存（创建 / 编辑）
     */
    public static function onPageSaveComplete(
        $wikiPage,
        $user,
        $summary,
        $flags,
        $revisionRecord,
        $editResult
    ): bool {

        $logger = LoggerFactory::getInstance( 'IndexNowNotifier' );
        $title = $wikiPage->getTitle();
        $url = $title->getFullURL();

        $logger->info( 'PageSaveComplete triggered.', [
            'title' => $title->getPrefixedText(),
            'page_id' => $wikiPage->getId(),
            'user' => $user ? $user->getName() : 'unknown'
        ] );

        return self::submitToIndexNow( $url );
    }

    /**
     * 页面移动
     */
    public static function onPageMoveComplete(
        $oldTitle,
        $newTitle,
        $user,
        $pageId,
        $redirId,
        $reason,
        $revision
    ): bool {

        $logger = LoggerFactory::getInstance( 'IndexNowNotifier' );

        $logger->info( 'PageMoveComplete triggered.', [
            'old' => $oldTitle->getPrefixedText(),
            'new' => $newTitle->getPrefixedText(),
            'user' => $user ? $user->getName() : 'unknown',
            'page_id' => $pageId
        ] );

        return self::submitToIndexNow( $newTitle->getFullURL() );
    }

    /**
     * 页面删除
     */
    public static function onPageDeleteComplete(
        $wikiPage,
        $user,
        $reason,
        $id,
        $content,
        $logEntry
    ): bool {
        $logger = LoggerFactory::getInstance( 'IndexNowNotifier' );
        // 兼容新版 MediaWiki：$wikiPage 可能是 ProperPageIdentity 而非 WikiPage
        $title = Title::newFromPageIdentity( $wikiPage );
        $logger->info( 'PageDeleteComplete triggered.', [
            'title' => $title->getPrefixedText(),
            'page_id' => $id,
            'user' => $user ? $user->getName() : 'unknown'
        ] );
        return self::submitToIndexNow( $title->getFullURL() );
    }
}
