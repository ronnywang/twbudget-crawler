<?php


$ref_list = new StdClass;
$ref = array();
$cat = null;
$code = null;
$amount = null;
$topname = null;
$depname = null;
$depcat = null;

$handle_rows = function($values, $type) use (&$ref_list, &$ref, &$cat, &$topname, &$depname, &$depcat, &$code, &$amount) {
    if (str_Replace(' ', '', $values['科目名稱']) == '合計') {
        return;
    } elseif (preg_match('#^\(.*\)$#', $values['科目名稱'])) {
        return;
    }

    $rightest_field = null;
    foreach (array('節', '目', '項', '款') as $f) {
        if ($values[$f]) {
            $rightest_field = $f;
            break;
        }
    }
    if ('款' == $rightest_field) {
        $ref = array($values['款']);
        if ($type == '機關') {
            $topname = $values['科目名稱'];
        }
        $depname = null;
        $depcat = null;
        $cat = null;
        return;
    } elseif ('項' == $rightest_field) {
        $ref = array($ref[0], $values['項']);
        $depname = $values['科目名稱'];
        $cat = null;
        return;
    } elseif ('目' == $rightest_field) {
        $ref = array($ref[0], $ref[1], $values['目']);
        $code = $values['科目編號'];
        $depcat = $values['科目名稱'];

        if (!$depcat) {
            print_r($values);
            exit;
        }

        $year = $values['年'] + 1911;
        $ref_id = $year . ':' . implode('.', $ref);
        if (!property_exists($ref_list, $ref_id)) {
            $ref_list->{$ref_id} = array(
                'year' => $year,
                'topname' => $topname,
                'depcat' => $depcat,
                'depname' => $depname,
                'cat' => $cat,
                'amount' => floatval($values['本年預算數']) * 1000,
                'code' => $code,
                'nochild' => true,
            );
        } else {
            if (is_null($ref_list->{$ref_id}['cat'])) {
                $ref_list->{$ref_id}['cat'] = $cat;
            }
            if (is_null($ref_list->{$ref_id}['topname'])) {
                $ref_list->{$ref_id}['topname'] = $topname;
            }
        }
        return;
    } elseif (is_null($rightest_field) or $values['節'] == 999) {
        $cat = $values['科目名稱'];
        return;
    } elseif ($values['節']) {
        $ref[3] = $values['節'];
        $name = $values['科目名稱'];
        $year = $values['年'] + 1911;
        $ref_id = $year . ':' . implode('.', $ref);
        if (!property_exists($ref_list, $ref_id)) {
            $ref_list->{$ref_id} = array_merge(
                $ref_list->{$year . ':' . implode('.', array_slice($ref, 0, 3))},
                array('name' => $name)
            );
            $ref_list->{$ref_id}['code'] = $code;
            $ref_list->{$ref_id}['amount'] = floatval($values['本年預算數']) * 1000;
            unset($ref_list->{$year . ':' . implode('.', array_slice($ref, 0, 3))}['nochild']);
        } 
        return;
    }
    print_r($ref_list);
    print_r($values);
    exit;
};

/*
$fp = gzopen('預算案-歲出政事別預算表.csv.gz', 'r');
$columns = fgetcsv($fp);
while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    $handle_rows($values, '政事');
}
fclose($fp);
 */

$fp = gzopen('預算案-歲出機關別預算表.csv.gz', 'r');
$columns = fgetcsv($fp);
while ($rows = fgetcsv($fp)) {
    $values = array_combine($columns, $rows);
    $handle_rows($values, '機關');
}
fclose($fp);

$year_fps = array();
$columns = explode(',', 'year,code,amount,name,topname,depname,depcat,cat,ref');
$cat_map = array();
foreach ($ref_list as $ref => $record) {
    if (!array_key_exists('nochild', $record)) {
        continue;
    }
    list($year, $ref) = explode(':', $ref);
    if (!array_key_Exists($year, $year_fps)) {
        $year_fps[$year] = fopen(__DIR__ . "/預算案/tw{$year}ap.csv", 'w');
        fputcsv($year_fps[$year], $columns);
    }
    $record['ref'] = $ref;
    if (!array_key_Exists('name', $record)) {
        $record['name'] = '無細項';
    }
    $cat_id = substr($record['code'], 0, 2);
    if (!array_key_exists($record['cat'], $cat_map)) {
        $cat_map[$record['cat']] = $cat_id;
    } elseif ($cat_map[$record['cat']] != $cat_id) {
        $record['code'] = $cat_map[$record['cat']] . substr($record['code'], 2);
    }
    fputcsv($year_fps[$year], array_map(function($k) use ($record) { return $record[$k]; }, $columns));
}

array_map('fclose', $year_fps);
