<?php

class Parser
{
    public function fetch($url)
    {
        if (strpos($url, 'http') !== 0) {
            $url = "https://www.dgbas.gov.tw/" . $url;
        }
        if (strpos($url, 'http://') === 0) {
            $url = str_replace('http://', 'https://', $url);
        }
        $cache_file = __DIR__ . "/cache-case/" . crc32($url);
        if (!file_exists($cache_file) or !filesize($cache_file)) {
            file_put_contents($cache_file, file_get_contents($url));
        }
        return $cache_file;
    }

    public function getYearList()
    {
        $url = 'https://www.dgbas.gov.tw/ct.asp?xItem=26269&CtNode=5389&mp=1';
        $year_list_file = $this->fetch($url);

        $doc = new DOMDocument;
        @$doc->loadHTML(file_get_contents($year_list_file));

        $result = array();
        foreach ($doc->getElementsByTagName('table')->item(0)->getElementsByTagName('tr') as $tr_dom) {
            $td_doms = $tr_dom->getElementsByTagName('td');
            if (!$td_doms->length) {
                continue;
            }

            $year = $td_doms->item(0)->nodeValue;
            $url = $td_doms->item(1)->getElementsByTagName('a')->item(0)->getAttribute('href');

            $result[] = array(intval($year), $url);
        }
        return $result;
    }

    public function fetchDetail($year, $url) 
    {
        error_log("抓取 {$year} 年三張表 {$url}");
        $year_file = $this->fetch($url);
        $doc = new DOMDocument;
        @$doc->loadHTML(file_get_contents($year_file));

        $table_p_dom = null;
        foreach ($doc->getElementsByTagName('p') as $p_dom) {
            if (strpos(trim($p_dom->nodeValue), '歲入來源別預算總表') === 0) {
                $table_p_dom = $p_dom;
                break;
            }
        }

        if (is_null($table_p_dom)) {
            throw new Exception("找不到 歲入來源別預算總表 所在的 p tag");
        }

        $targets = array(
            '歲入來源別預算表' => true,
            '歲出政事別預算表' => true,
            '歲出機關別預算表' => true,
        );
        $founds = array();
        foreach ($table_p_dom->getElementsByTagName('a') as $a_dom) {
            foreach ($targets as $t => $nonce) {
                if (strpos($a_dom->getAttribute('href'), "{$t}.xls")) {
                    $founds[] = array($t, $a_dom->getAttribute('href'));
                    unset($targets[$t]);
                }
            }
        }

        if ($targets) {
            throw new Exception("仍有以下幾項找不到: " . implode(',', array_keys($targets)));
        }

        $type_records = array();
        foreach ($founds as $type_url) {
            list($type, $excel_url) = $type_url;
            if ($type == '歲入來源別預算表') {
                continue; // TODO: 歲入先不處理
            } 
            // excel url 特別處理不要包含中文
            $excel_url = str_replace('http://www.dgbas.gov.tw', 'https://www.dgbas.gov.tw', $excel_url);
            $excel_url = iconv('utf-8', 'big5', $excel_url);
            $excel_file = $this->fetch($excel_url);
            error_log("parsing {$excel_file} {$excel_url}");
            $csv_file = __DIR__ . "/cache-case/csv-" . crc32($excel_file);
            if (!file_exists($csv_file) or !filesize($csv_file)) {
                $cmd = sprintf("./node_modules/.bin/xlsx --list-sheets %s", escapeshellarg($excel_file));
                $sheets = explode("\n", trim(`$cmd`));
                if (count($sheets) != 1) {
                    throw new Exception("不只一個 sheet");
                }
                system(sprintf("./node_modules/.bin/xlsx %s > %s", escapeshellarg($excel_file), escapeshellarg($csv_file)));
            }

            error_log($csv_file);
            $fp = fopen($csv_file, 'r');
            $column_type = 0;
            while ($columns = fgetcsv($fp)) {
                if (strpos(implode(',', array_slice($columns, 0, 4)), '款,項,目,節') !== FALSE) {
                    if (implode(',', array_slice($columns, 4, 3)) == '科目編號,科目名稱,本年度預算數') {
                        $column_type = 1;
                        break;
                    } elseif (str_replace(' ', '', $columns[4]) == '名稱及編號' and $prev_columns[5] == '本年度預算數') {
                        $column_type = 2;
                        break;
                    } else {
                        print_r($columns);
                        $columns = fgetcsv($fp);
                        print_r($columns);
                        exit;
                    }
                }
                $prev_columns = $columns;
            }

            if (!$columns) {
                throw new Exception("找不到 款,項,目,節,科目編號,科目名稱,本年度預算數");
            }

            $records = array();
            $columns = explode(',', '款,項,目,節,科目編號,科目名稱,本年預算數,上年預算數,前年預算數');
            while ($rows = fgetcsv($fp)) {
                $rows = array_map('trim', $rows);
                if (implode('', $rows) == '') {
                    continue;
                }
                $values = array();
                foreach (array('款','項','目','節') as $f) {
                    $values[$f] = array_shift($rows);
                }
                if ($column_type == 1) {
                    $values['科目編號'] = array_shift($rows);
                    $values['科目名稱'] = array_shift($rows);
                } elseif ($column_type == 2) {
                    $name = array_shift($rows);
                    $name = str_replace('　', '', $name);

                    if (strpos($name, "\n") !== false) {
                        list($id, $name) = explode("\n", $name);
                        $values['科目編號'] = trim($id);
                        $values['科目名稱'] = trim($name);
                    } else {
                        $values['科目編號'] = '';
                        $values['科目名稱'] = trim($name);
                    }
                }
                foreach (array('本年預算數', '上年預算數', '前年預算數') as $f) {
                    $amount = trim(array_shift($rows));
                    $amount = str_replace(',', '', $amount);
                    if (is_numeric($amount)) {
                    } elseif ($amount == '-') {
                        $amount = '';
                    } else {
                        print_r($values);
                        throw new Exception("$amount 不是個數字");
                    }
                    $values[$f] = $amount;
                }
                $records[] = array_values($values);
            }

            $type_records[$type] = array($columns, $records);
        }

        return $type_records;
    }

    public function main()
    {
        $year_urls = $this->getYearList();
        $fps = array();
        foreach ($year_urls as $year_url) {
            list($year, $url) = $year_url;
            if ($year == 94) {
                break;
            }
            $type_records = $this->fetchDetail($year, $url);
            foreach ($type_records as $type => $column_records) {
                list($columns, $records) = $column_records;
                if (!array_key_exists($type, $fps)) {
                    $fps[$type] = gzopen("預算案-{$type}.csv.gz", 'w');
                    fputcsv($fps[$type], array_merge(array('年'), $columns));
                }
                foreach ($records as $record) {
                    fputcsv($fps[$type], array_merge(array($year), $record));
                }
            }
            array_map('fflush', $fps);
        }
        array_map('fclose', $fps);

    }
}

$p = new Parser;
$p->main();
