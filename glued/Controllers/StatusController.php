<?php

declare(strict_types=1);

namespace Glued\Controllers;

use DOMDocument;
use Glued\Lib\Controllers\AbstractBlank;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Linfo\Linfo;


class StatusController extends AbstractBlank
{

    /**
     * Return system info via the Linfo library. NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function sysinfo(Request $request, Response $response, array $args = []): Response {
        $linfo = new Linfo(['temps'=> ['hwmon' => true]]);
        $parser = $linfo->getParser();
        $parser->determineCPUPercentage();
        $methods = ['Hostname', 'OS', 'Kernel', 'Distro', 'Uptime', 'Virtualization', 'CPU', 'HD', 'Ram', 'Load', 'Net', 'Temps'];
        foreach ($methods as $m) {
            $method = 'get' . $m;
            $data[strtolower($m)] = $parser->$method();
        }
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns PHP's get_defined_constants(true). NOTE that this method must me behind auth.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function phpconst(Request $request, Response $response, array $args = []): Response {
        $arr = get_defined_constants(true);
        $response->getBody()->write(json_encode($arr, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-type', 'application/json');
    }

    /**
     * Reflects a client request.
     * @param  Request  $request  
     * @param  Response $response 
     * @param  array    $args     
     * @return Response Json result set.
     */
    public function reflect_request(Request $request, Response $response, array $args = []): Response {
        $data = getallheaders();
        $data['http']['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $data['http']['REMOTE_PORT'] = $_SERVER['REMOTE_PORT'] ?? '';
        $data['http']['REMOTE_USER'] = $_SERVER['REMOTE_USER'] ?? '';
        $data['http']['REMOTE_X-FORWARDED-FOR'] = $_SERVER['X-FORWARDED-FOR'] ?? '';
        $data['http']['REMOTE_X-REAL-IP'] = $_SERVER['X-REAL-IP'] ?? '';
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns server internal state
     * @param  Request  $request
     * @param  Response $response
     * @param  array    $args
     * @return Response Json result set.
     */
    public function server(Request $request, Response $response, array $args = []): Response {
        $data = $_SERVER;
        $this->logger->warning("core.status.server method invoked.");
        return $response->withJson($data, options: JSON_UNESCAPED_SLASHES);
    }

    public function phpinfo(Request $request, Response $response, array $args = []): Response
    {
        // Capture the phpinfo() output
        ob_start();
        phpinfo();
        $html = ob_get_clean();
        $sections = [];

        // Load HTML into DOMDocument
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();
        $tables = $doc->getElementsByTagName('table');

        foreach ($tables as $table) {
            // Determine section name by looking for a preceding header (h1 or h2)
            $sectionName = 'General';
            $prev = $table->previousSibling;
            while ($prev && $prev->nodeType !== XML_ELEMENT_NODE) {
                $prev = $prev->previousSibling;
            }
            if ($prev && in_array($prev->nodeName, ['h1', 'h2'])) {
                $sectionName = trim($prev->textContent);
            }
            // Stop processing when reaching PHP Credits header.
            if ($sectionName === "PHP Credits") { break; }
            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = [];
            }

            $tableData = [];
            $hasHeader = false;
            $headerColumns = [];
            // Iterate over each table row
            foreach ($table->getElementsByTagName('tr') as $tr) {
                // Check if this row is a header row (contains <th>)
                $thElements = $tr->getElementsByTagName('th');
                if ($thElements->length > 0) {
                    $hasHeader = true;
                    foreach ($thElements as $th) {
                        $headerColumns[] = trim($th->textContent);
                    }
                    continue; // Skip header row
                }
                // Get <td> cells
                $cells = $tr->getElementsByTagName('td');
                if ($cells->length === 0) {
                    continue;
                }

                if ($cells->length === 1) {
                    // Attempt to split a one-cell row into a key/value pair.
                    $text = trim($cells->item(0)->textContent);
                    if (preg_match('/^(.+?)\s+([\d\.]+)$/', $text, $matches)) {
                        $key = trim($matches[1]);
                        $value = trim($matches[2]);
                        $tableData[$key] = $value;
                    } else {
                        $tableData[] = $text;
                    }
                } elseif ($hasHeader && count($headerColumns) === 3 && $cells->length === 3) {
                    // Row with key / local / master format
                    $key    = trim($cells->item(0)->textContent);
                    $local  = trim($cells->item(1)->textContent);
                    $master = trim($cells->item(2)->textContent);
                    $tableData[$key] = ['local' => $local, 'master' => $master];
                } elseif ($cells->length === 2) {
                    // Simple key/value row
                    $key   = trim($cells->item(0)->textContent);
                    $value = trim($cells->item(1)->textContent);
                    $tableData[$key] = $value;
                } else {
                    // Fallback: store all cell values as a list.
                    $rowData = [];
                    foreach ($cells as $cell) {
                        $rowData[] = trim($cell->textContent);
                    }
                    $tableData[] = $rowData;
                }
            }

            if (!empty($tableData)) {
                $sections[$sectionName][] = $tableData;
            }
        }

        // Helper: determine if an array is associative.
        $isAssociative = function (array $arr): bool {
            if ([] === $arr) return false;
            return array_keys($arr) !== range(0, count($arr) - 1);
        };

        // Process miscellaneous (non-associative) items into an object with deduced keys.
        // This function does not flatten multi-dimensional arrays.
        $processMisc = function (array $misc) use ($isAssociative) {
            $result = [];
            $counter = 0;
            foreach ($misc as $item) {
                $deducedKey = null;
                if (is_string($item)) {
                    $text = $item;
                } elseif (is_array($item)) {
                    if (count($item) === 1 && is_string($item[0])) {
                        $text = $item[0];
                    }
                    // If the first element is itself an array and its first element is a string,
                    // use that for key deduction, but leave the item intact.
                    elseif (!empty($item) && is_array($item[0]) && isset($item[0][0]) && is_string($item[0][0])) {
                        $candidate = $item[0][0];
                        if (stripos($candidate, 'Zend Engine') !== false) {
                            $deducedKey = 'zend';
                        } elseif (stripos($candidate, 'Phar') !== false || stripos($candidate, 'PHP_Archive') !== false) {
                            $deducedKey = 'phar';
                        } elseif (stripos($candidate, 'Development Helpers') !== false || stripos($candidate, 'Xdebug') !== false) {
                            $deducedKey = 'xdebug';
                        }
                        $text = $item; // Leave as array.
                    }
                    // If it's a one-dimensional array and all elements are strings, join them.
                    elseif (array_reduce($item, function ($carry, $v) {
                        return $carry && is_string($v);
                    }, true)) {
                        $text = implode(" ", $item);
                    } else {
                        // Otherwise, leave as is.
                        $text = $item;
                    }
                } else {
                    $text = strval($item);
                }
                // If we haven't deduced a key yet and $text is a string, try to deduce one.
                if ($deducedKey === null && is_string($text)) {
                    if (stripos($text, 'Zend Engine') !== false) {
                        $deducedKey = 'zend';
                    } elseif (stripos($text, 'Phar') !== false || stripos($text, 'PHP_Archive') !== false) {
                        $deducedKey = 'phar';
                    } elseif (stripos($text, 'Xdebug') !== false || stripos($text, 'Development Helpers') !== false) {
                        $deducedKey = 'xdebug';
                    }
                }
                if ($deducedKey === null) {
                    $deducedKey = "misc_$counter";
                }
                // If $text is an array (i.e. multi-dimensional), leave it as-is.
                if (is_array($text)) { $result[$deducedKey] = $text; }
                else { $result[$deducedKey] = $text; }
                $counter++;
            }
            return $result;
        };

        // Post-process each section: merge all associative tables into one object.
        foreach ($sections as $sectionName => $tables) {
            $merged = [];
            $misc = [];
            foreach ($tables as $tbl) {
                if (is_array($tbl) && $isAssociative($tbl)) {
                    $merged = array_merge($merged, $tbl);
                } else {
                    $misc[] = $tbl;
                }
            }
            if (!empty($merged)) {
                if (!empty($misc)) {
                    $merged["misc"] = $processMisc($misc);
                }
                $sections[$sectionName] = $merged;
            } else {
                $sections[$sectionName] = $processMisc($misc);
            }
        }

        // In the "General" section, move all keys whose value is an array with "local" and "master"
        // into a new sub-object named "directives".
        if (isset($sections["General"]) && is_array($sections["General"])) {
            $directives = [];
            foreach ($sections["General"] as $key => $value) {
                if (is_array($value) && isset($value['local']) && isset($value['master'])) {
                    $directives[$key] = $value;
                    unset($sections["General"][$key]);
                }
            }
            if (!empty($directives)) {
                $sections["General"]["directives"] = $directives;
            }
        }

        $json = json_encode($sections, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }




}
