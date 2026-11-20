<?php

declare(strict_types=1);

namespace App\\Workflow;

/**
 * Decodes a JSON request body while enforcing an object at the top level.
 *
 * Nested JSON objects are returned as associative arrays for the existing
 * application-service contracts.
 */
final class JsonObjectPayload
{
    /**
     * @return array<string, mixed>|false False when the body is invalid JSON
     *                                     or its top-level value is not an object.
     */
    public static function decode(string $content): array|false
    {
        try {
            $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
            if (!$decoded instanceof \\stdClass) {
                return false;
            }

            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\\JsonException) {
            return false;
        }

        return is_array($payload) ? $payload : false;
    }
}
