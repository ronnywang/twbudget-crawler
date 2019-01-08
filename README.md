# twbudget-crawler

這邊是 budget.g0v.tw 自 2018 年以後資料抓取的程式，主要是產生 [twbudget/app/assets/data](https://github.com/g0v/twbudget/tree/master/app/assets/data) 需要的 csv 檔

用法
----
* npm install
  * 安裝 nodejs 的 xlsx ，以便把 excel 轉成 csv
* php parse-case.php
  * 從 [中央政府總預算](https://www.dgbas.gov.tw/ct.asp?xItem=26269&CtNode=5389&mp=1) 抓取歷年預算案內的 歲入來源別預算表、歲出政事別預算表、歲出機關別預算表 的 Excel 檔，暫存在 cache-case/ 資料夾內，並轉換成 csv，並把結果合併成 預算案-歲出政事別預算表.csv.gz 和 預算案-歲出機關別預算表.csv.gz
* php gen-case-csv.php
  * 將 parse-case.php 產生的 預算案-歲出政事別預算表.csv.gz 和 預算案-歲出機關別預算表.csv.gz 依照年份產生在 預算案/ 的 CSV 檔
