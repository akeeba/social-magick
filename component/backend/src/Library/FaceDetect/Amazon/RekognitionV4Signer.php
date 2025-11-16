<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\Amazon;

defined('_JEXEC') || die;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Minimal AWS Signature Version 4 signer for Amazon Rekognition JSON API requests.
 *
 * This class creates the required headers to authenticate POST requests sent to
 * the Rekognition endpoint (application/x-amz-json-1.1 protocol). No third-party
 * libraries are used.
 *
 * @since 3.0.0
 */
class RekognitionV4Signer
{
    private const SERVICE      = 'rekognition';
    private const CONTENT_TYPE = 'application/x-amz-json-1.1';

    /**
     * Build the HTTP headers for a signed Rekognition request.
     *
     * @param  string                   $targetAction     Rekognition action. Either the short form (e.g. "DetectFaces")
     *                                                   or the fully-qualified target (e.g. "RekognitionService.DetectFaces").
     * @param  string                   $region           AWS region (e.g. "us-east-1").
     * @param  string                   $accessKeyId      AWS access key ID.
     * @param  string                   $secretAccessKey  AWS secret access key.
     * @param  string                   $payload          JSON payload to send.
     * @param  string|null              $sessionToken     Optional session token for temporary credentials.
     * @param  DateTimeInterface|null   $now              Optional time (UTC). If null, current UTC time is used.
     * @param  string|null              $hostOverride     Optional host override. If null, default host is used.
     *
     * @return array<string,string> The headers to include in the HTTP request.
     */
    public function buildRequestHeaders(
        string $targetAction,
        string $region,
        string $accessKeyId,
        string $secretAccessKey,
        string $payload,
        ?string $sessionToken = null,
        ?DateTimeInterface $now = null,
        ?string $hostOverride = null,
    ): array {
        $host      = $hostOverride ?: $this->getEndpointHost($region);
        $target    = str_contains($targetAction, '.') ? $targetAction : ('RekognitionService.' . $targetAction);

        // Dates
        $now      = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now      = $now->setTimezone(new DateTimeZone('UTC'));
        $amzDate  = $now->format('Ymd\THis\Z');
        $dateStamp= $now->format('Ymd');

        // Payload hash
        $payloadHash = hash('sha256', $payload ?? '', false);

        // Prepare headers used for signing (lowercase names for canonical form)
        $headersCanonical = [
            'content-type'         => self::CONTENT_TYPE,
            'host'                 => $host,
            'x-amz-date'           => $amzDate,
            'x-amz-target'         => $target,
            'x-amz-content-sha256' => $payloadHash,
        ];

        if (!empty($sessionToken)) {
            $headersCanonical['x-amz-security-token'] = $sessionToken;
        }

        // Create canonical request
        ksort($headersCanonical, SORT_STRING);
        $canonicalHeaders = '';
        foreach ($headersCanonical as $name => $value) {
            $canonicalHeaders .= $name . ':' . $this->normalizeHeaderValue($value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headersCanonical));

        $canonicalRequest = implode("\n", [
            'POST',               // HTTP method
            '/',                  // Canonical URI
            '',                   // Canonical query string
            $canonicalHeaders,    // Canonical headers (must end with a newline)
            $signedHeaders,       // Signed headers (semicolon-separated)
            $payloadHash,         // Hex SHA-256 of payload
        ]);

        // String to sign
        $algorithm      = 'AWS4-HMAC-SHA256';
        $credentialScope= $dateStamp . '/' . $region . '/' . self::SERVICE . '/aws4_request';
        $stringToSign   = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest, false),
        ]);

        // Signature
        $signingKey = $this->getSigningKey($secretAccessKey, $dateStamp, $region, self::SERVICE);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        // Build final headers with normal casing
        $headers = [
            'Host'                 => $host,
            'Content-Type'         => self::CONTENT_TYPE,
            'X-Amz-Date'           => $amzDate,
            'X-Amz-Target'         => $target,
            'X-Amz-Content-Sha256' => $payloadHash,
            'Authorization'        => $algorithm
                . ' Credential=' . $accessKeyId . '/' . $credentialScope
                . ', SignedHeaders=' . $signedHeaders
                . ', Signature=' . $signature,
        ];

        if (!empty($sessionToken)) {
            $headers['X-Amz-Security-Token'] = $sessionToken;
        }

        return $headers;
    }

    /**
     * Get the HTTPS endpoint URL for Rekognition in a specific region.
     */
    public function getEndpointUrl(string $region): string
    {
        return 'https://' . $this->getEndpointHost($region) . '/';
    }

    /**
     * Get the hostname for Rekognition in a specific region.
     */
    public function getEndpointHost(string $region): string
    {
        return 'rekognition.' . $region . '.amazonaws.com';
    }

    /**
     * Normalize a header value: trim and collapse sequential whitespace.
     */
    private function normalizeHeaderValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value ?? '') ?? '';

        return $value;
    }

    /**
     * Derive the SigV4 signing key.
     */
    private function getSigningKey(string $secretAccessKey, string $dateStamp, string $region, string $service): string
    {
        $kSecret  = 'AWS4' . $secretAccessKey;
        $kDate    = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }
}
