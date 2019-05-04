
Model name:          Intel(R) Core(TM) i7-8700 CPU @ 3.20GHz

PHP 7.3.4 (cli) (built: Apr  5 2019 12:40:33) ( NTS )
Copyright (c) 1997-2018 The PHP Group
Zend Engine v3.3.4, Copyright (c) 1998-2018 Zend Technologies
    with Zend OPcache v7.3.4, Copyright (c) 1999-2018, by Zend Technologies

PHPUnit 7.3.5 by Sebastian Bergmann and contributors.

.

### -> TestHQueryStress::test_construct_and_index ()
  1)          load_file( 3.113MiB )  	in	  1'497µs	3'192KiB RAM
  2)         new hQuery( 3.441MiB )   	in	 15'925µs	3'537KiB RAM
  3)      hQuery->index( 50'924 tags )	in	120'900µs	19'079KiB RAM
  4)     Original Charset: WINDOWS-1251
.

### -> TestHQueryStress::test_find ()
  1)  count($c.find('span'))         	= 10131 in     430µs ($c=doc),   1'173µs ($c=body)  mem: 640KiB
  2)  count($c.find('.ch-title'))    	=  5616 in     396µs ($c=doc),     798µs ($c=body)  mem: 320KiB
  3)  count($c.find('.even'))        	=  2808 in     267µs ($c=doc),     406µs ($c=body)  mem: 160KiB
  4)  count($c.find('.row'))         	=  1464 in     174µs ($c=doc),     241µs ($c=body)  mem: 80.2KiB
  5)  count($c.find('a'))            	=  2204 in      69µs ($c=doc),     182µs ($c=body)  mem: 160KiB
  6)  count($c.find('img'))          	=   730 in      23µs ($c=doc),      60µs ($c=body)  mem: 40.2KiB
  7)  count($c.find('a img'))        	=   727 in     330µs ($c=doc),     365µs ($c=body)  mem: 47.7KiB
  8)  count($c.find('a>img'))        	=   727 in   1'889µs ($c=doc),   3'626µs ($c=body)  mem: 80.2KiB
  9)  count($c.find('a>img:parent')) 	=   727 in   4'190µs ($c=doc),   4'334µs ($c=body)  mem: 40.6KiB
 10)  count($c.find('.first'))       	=   720 in      42µs ($c=doc),      79µs ($c=body)  mem: 40.2KiB
 11)  count($c.find('.first:parent'))	=   720 in     781µs ($c=doc),     816µs ($c=body)  mem: 40.2KiB
 12)  count($c.find('.first:next'))  	=   720 in   7'284µs ($c=doc),   7'319µs ($c=body)  mem: 40.2KiB
 13)  count($c.find('img.click'))    	=     6 in      11µs ($c=doc),       9µs ($c=body)  mem: 0.52KiB
.

### -> TestDOMCrawler::test_construct_and_index ()
  1)          load_file( 3.113MiB )  	in	  1'459µs	3'192KiB RAM
  2)         new hQuery( 3.441MiB )   	in	 16'323µs	3'525KiB RAM
  3)      hQuery->index( 50'924 tags )	in	130'306µs	19'079KiB RAM
  4)     Original Charset: WINDOWS-1251
  5)     new DOMCrawler( 3.113MiB )  	in	189'465µs	129KiB RAM
.

### -> TestDOMCrawler::test_find ()
  1)  #document

 |     Selector     | found  |   hQuery   |  Crawler   | faster |  hQuery   |  Crawler  | smaller |
 | ---------------- | ------ | ---------- | ---------- | ------ | --------- | --------- | ------- |
 | span             |  10131 |      459µs |  130'824µs | x285   |    640KiB |  1'848KiB | x2.9    | 
 | span.glyphicon   |   2171 |      379µs |   19'672µs | x52    |    160KiB |    323KiB | x2      | 
 | div              |   8074 |      293µs |   87'342µs | x298   |    320KiB |    954KiB | x3      | 
 | p                |   2165 |       83µs |   10'189µs | x123   |    160KiB |    318KiB | x2      | 
 | td               |   5629 |      210µs |   43'241µs | x206   |    320KiB |    744KiB | x2.3    | 
 | tr               |   5623 |      222µs |   46'262µs | x208   |    320KiB |    743KiB | x2.3    | 
 | table            |    723 |       36µs |    5'023µs | x140   |   40.2KiB |   98.3KiB | x2.4    | 
 | script           |      3 |       10µs |    6'233µs | x622   |   0.52KiB |   0.78KiB | x1.5    | 
 | form             |    723 |       34µs |    4'725µs | x139   |   40.2KiB |   98.3KiB | x2.4    | 
 | table tr         |   5623 |      597µs |  594'164µs | x995   |    320KiB |    773KiB | x2.4    | 
 | table>tr         |   5623 |    2'248µs |   41'616µs | x19    |    320KiB |    743KiB | x2.3    | 
 | tr td            |   5629 |    1'027µs |  566'685µs | x552   |    320KiB |    744KiB | x2.3    | 
 | tr>td            |   5629 |    5'224µs |   45'237µs | x9     |    640KiB |    744KiB | x1.2    | 
 | .ch-title        |   5616 |      426µs |   87'007µs | x204   |    320KiB |    743KiB | x2.3    | 
 | .even            |   2808 |      287µs |   64'361µs | x224   |    160KiB |    374KiB | x2.3    | 
 | .row             |   1464 |      193µs |   49'947µs | x259   |   80.2KiB |    194KiB | x2.4    | 
 | a                |   2204 |      101µs |   12'677µs | x126   |    160KiB |    322KiB | x2      | 
 | img              |    730 |       32µs |    5'432µs | x170   |   40.2KiB |   98.9KiB | x2.5    | 
 | a img            |    727 |      271µs |   18'668µs | x69    |   40.2KiB |   98.7KiB | x2.5    | 
 | a>img            |    727 |    3'569µs |    5'159µs | x1     |   80.2KiB |   98.7KiB | x1.2    | 
 | .first           |    720 |       50µs |   46'657µs | x932   |   40.2KiB |   98.1KiB | x2.4    | 
 | .first:next      |    720 |    7'355µs |   46'799µs | x6     |   40.2KiB |   98.1KiB | x2.4    | 
 | img.click        |      6 |       24µs |    4'516µs | x189   |   0.52KiB |    1.0KiB | x2      | 
 | #current_page    |      1 |      780µs |   17'938µs | x23    |   0.52KiB |    5.2KiB | x9.9    | 
 | div#current_page |      1 |      869µs |    7'507µs | x9     |   0.52KiB |   0.61KiB | x1.2    | 
 | ---------------- | ------ | ---------- | ---------- | ------ | --------- | --------- | ------- |
 |         Average: |   2939 |      991µs |   78'715µs | x79    |    183KiB |    411KiB | x2      | 
 |           Total: |  73470 |       25ms |    1'968ms | -      |  4'567KiB | 10'264KiB | -       | 

.

### -> TestDOMCrawler::test_body_find ()
  1)  body

 |     Selector     | found  |   hQuery   |  Crawler   | faster |  hQuery   |  Crawler  | smaller |
 | ---------------- | ------ | ---------- | ---------- | ------ | --------- | --------- | ------- |
 | span             |  10131 |    1'110µs |  131'736µs | x119   |    640KiB |  1'387KiB | x2.2    | 
 | span.glyphicon   |   2171 |      506µs |   22'236µs | x44    |    160KiB |    319KiB | x2      | 
 | div              |   8074 |      717µs |   85'638µs | x119   |    320KiB |    954KiB | x3      | 
 | p                |   2165 |      210µs |    9'760µs | x46    |    160KiB |    318KiB | x2      | 
 | td               |   5629 |      506µs |   42'873µs | x85    |    320KiB |    744KiB | x2.3    | 
 | tr               |   5623 |      496µs |   41'479µs | x84    |    320KiB |    743KiB | x2.3    | 
 | table            |    723 |       67µs |    4'264µs | x64    |   40.2KiB |   98.3KiB | x2.4    | 
 | script           |      3 |       12µs |    3'262µs | x268   |   0.52KiB |   0.78KiB | x1.5    | 
 | form             |    723 |       63µs |    4'118µs | x65    |   40.2KiB |   98.3KiB | x2.4    | 
 | table tr         |   5623 |      606µs |  580'712µs | x958   |    320KiB |    743KiB | x2.3    | 
 | table>tr         |   5623 |    3'998µs |   40'791µs | x10    |    320KiB |    743KiB | x2.3    | 
 | tr td            |   5629 |    1'351µs |  559'469µs | x414   |    320KiB |    744KiB | x2.3    | 
 | tr>td            |   5629 |    5'450µs |   45'002µs | x8     |    640KiB |    744KiB | x1.2    | 
 | .ch-title        |   5616 |      728µs |   84'931µs | x117   |    320KiB |    743KiB | x2.3    | 
 | .even            |   2808 |      457µs |   58'202µs | x127   |    160KiB |    374KiB | x2.3    | 
 | .row             |   1464 |      271µs |   47'647µs | x176   |   80.2KiB |    194KiB | x2.4    | 
 | a                |   2204 |      220µs |   12'987µs | x59    |    160KiB |    322KiB | x2      | 
 | img              |    730 |       82µs |    4'964µs | x61    |   40.2KiB |   98.9KiB | x2.5    | 
 | a img            |    727 |      387µs |   18'466µs | x48    |   40.2KiB |   98.7KiB | x2.5    | 
 | a>img            |    727 |    3'680µs |    4'973µs | x1     |   80.2KiB |   98.7KiB | x1.2    | 
 | .first           |    720 |       93µs |   44'334µs | x477   |   40.2KiB |   98.1KiB | x2.4    | 
 | .first:next      |    720 |    7'492µs |   46'621µs | x6     |   40.2KiB |   98.1KiB | x2.4    | 
 | img.click        |      6 |       29µs |    4'553µs | x157   |   0.52KiB |    1.0KiB | x2      | 
 | #current_page    |      1 |      766µs |   16'922µs | x22    |   0.52KiB |   0.61KiB | x1.2    | 
 | div#current_page |      1 |      787µs |    6'100µs | x8     |   0.52KiB |   0.61KiB | x1.2    | 
 | ---------------- | ------ | ---------- | ---------- | ------ | --------- | --------- | ------- |
 |         Average: |   2939 |    1'203µs |   76'882µs | x64    |    183KiB |    391KiB | x2      | 
 |           Total: |  73470 |       30ms |    1'922ms | -      |  4'567KiB |  9'764KiB | -       | 

.................                                            22 / 22 (100%)

Time: 4.49 seconds, Memory: 70.50MB

OK (22 tests, 282 assertions)
