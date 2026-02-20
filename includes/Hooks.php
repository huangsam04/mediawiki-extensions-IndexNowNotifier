<?php

namespace IndexNowNotifier;

use MediaWiki\MediaWikiServices;
use MediaWiki\Logger\LoggerFactory;

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
            $logger->error( 'Failed to parse host from URL.', [ 'url' => $url ] );
            return true;
        }

        $payloadArray = [
            'host' => $host,
            'key' => $key,
            'urlList' => [ $url ]
        ];

        $payload = json_encode( $payloadArray );

        if ( $payload === false ) {
            $logger->error( 'Failed to encode JSON payload.', [
                'json_error' => json_last_error_msg()
            ] );
            return true;
        }

        $logger->info( 'Submitting URL to IndexNow.', [
            'url' => $url
        ] );

        $ch = curl_init( 'https://api.indexnow.org/indexnow' );

        curl_setopt_array( $ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [ 'Content-Type: application/json' ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3
        ] );

        $response = curl_exec( $ch );

        if ( $response === false ) {
            $logger->error( 'cURL execution failed.', [
                'error' => curl_error( $ch )
            ] );
            curl_close( $ch );
            return true;
        }

        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        $logger->info( 'IndexNow response received.', [
            'http_code' => $httpCode,
            'response' => $response
        ] );

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
            'title' => $title->getPrefixedText()
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
            'new' => $newTitle->getPrefixedText()
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
        $title = $wikiPage->getTitle();

        $logger->info( 'PageDeleteComplete triggered.', [
            'title' => $title->getPrefixedText()
        ] );

        return self::submitToIndexNow( $title->getFullURL() );
    }
}