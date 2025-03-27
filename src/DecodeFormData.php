<?php

namespace Inilim\Request;

final class DecodeFormData
{
    /**
     * @param string $input value not changed
     * @return array{0:mixed[],1:mixed[]} 0 - $_POST | 1 - $_FILES
     */
    function __invoke(string &$input)
    {
        $files  = [];
        $data   = [];
        $unlink = [];
        // Fetch content and determine boundary
        $boundary = \substr($input, 0, \strpos($input, "\r\n"));
        // Fetch and process each part
        $parts = $input ? \array_slice(\explode($boundary, $input), 1) : [];
        foreach ($parts as &$part) {
            // If this is the last part, break
            if ($part == "--\r\n") {
                break;
            }
            // Separate content from headers
            $part = \ltrim($part, "\r\n");
            [$rawHeaders, $content] = \explode("\r\n\r\n", $part, 2);
            $part = '';
            $content = \substr($content, 0, \strlen($content) - 2);
            // Parse the headers list
            $rawHeaders = \explode("\r\n", $rawHeaders);
            $headers    = [];
            foreach ($rawHeaders as $header) {
                [$name, $value] = \explode(':', $header);
                $headers[\strtolower($name)] = \ltrim($value, ' ');
            }
            // Parse the Content-Disposition to get the field name, etc.
            if (isset($headers['content-disposition'])) {
                $filename = null;
                \preg_match(
                    '/^form-data; *name="([^"]+)"(; *filename="([^"]+)")?/',
                    $headers['content-disposition'],
                    $matches
                );
                $fieldName = $matches[1];
                $fileName  = (isset($matches[3]) ? $matches[3] : null);
                // If we have a file, save it. Otherwise, save the data.
                if ($fileName !== null) {
                    $localFileName = \tempnam(\sys_get_temp_dir(), 'sfy');
                    if ($localFileName === false) {
                        continue;
                    }
                    $unlink[] = $localFileName;
                    $putStatus = \file_put_contents($localFileName, $content);
                    if ($putStatus === false) {
                        continue;
                    }
                    $files = $this->transformData($files, $fieldName, [
                        'name'     => $fileName,
                        'type'     => $headers['content-type'],
                        'tmp_name' => $localFileName,
                        'error'    => 0,
                        'size'     => \filesize($localFileName)
                    ]);
                } else {
                    $data = $this->transformData($data, $fieldName, $content);
                }
            } // endif
        } // endforeach

        if ($unlink) {
            // register a shutdown function to cleanup the temporary file
            \register_shutdown_function(static function () use ($unlink) {
                foreach ($unlink as &$file) {
                    @\unlink($file);
                }
            });
        }

        return [$data, $files];
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>
     */
    protected function transformData(array $data, string $name, $value)
    {
        $isArray = \strpos($name, '[]');
        if ($isArray && (($isArray + 2) == \strlen($name))) {
            $name = \str_replace('[]', '', $name);
            $data[$name][] = $value;
        } else {
            $data[$name] = $value;
        }
        return $data;
    }
}
