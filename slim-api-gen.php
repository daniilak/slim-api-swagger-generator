#!/usr/bin/php
<?php
    $options = getopt("i:o:n::h");

    if ($argc < 3 || array_key_exists("h", $options)) {
        printUsage();
        die();
    }

    if (!file_exists($options["i"])) {
        die("Input file does not exist !\n");
    }

    $out_file = fopen($options["o"], "w");
    if (!$out_file) {
        die("Could not create output file. \n");
    }

    $src_file = fopen($options["i"], "r");
    $path = [];
    if ($src_file){
        while(($line = fgets($src_file)) !== false){

            preg_match("/^\\$[a-z]*->(group)\(\'\/(.*)\'/", $line, $route_data);
            if ($route_data) { 
                $tag = trim($route_data[2]);
            }

            preg_match("/(post|get)\('(.*)',(.*)::.*:(.*)'\)/", $line, $route_data);
            if ($route_data) { 
                $typeRequest = $route_data[1];
                $request = trim($route_data[2]);
                $class = $route_data[3];
                $tmp = explode("'", $route_data[4]);
                $func = $tmp[0];
                $a = $tag.$request;
                if (mb_substr($a, 0, 1) != "/") $a = "/".$a;
                preg_match_all("/{(\w+)}/",$a, $variablesInPath);
                $path[$a][$typeRequest] = [
                    "tags"=>[($tag == null) ? "null" : $tag],
                    "summary"=>"", 
                    "description"=>"",
                    "responses" => [
                        "200" => [
                            "description" => "",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "type" => "array",
                                        "items" => ["type"=>"string","enum"=>["success"]]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    
                ];
                $arr = parseClassFile($request, $class, $func, $variablesInPath[0]);
                if (count($arr) > 0) {
                    $path[$a][$typeRequest]["parameters"] = $arr;
                }

                
            }
        }

        fwrite($out_file, json_encode(
            [
                "openapi" => "3.0.0",
                "info" => [
                    "title"     =>  "API",
                    "version"   =>  "3.0",
                ],
                "servers" => [[
                    "url"           =>  "https://beta.cryptorobotics.io/",
                    "description"   =>  "BETA"
                ]],
                "paths" => $path
            ]
        ));
    } else {
        die("Could not open input file");
    }

    fclose($out_file);
    fclose($src_file);


    /* Declarations */

    function printUsage(){
        print("Slim simple documentation generator.\n");
        print("Usage: slim_doc.php -i[input_file] -o[output_file]\n");
        print("Optional Parameters: \n\t-t[template] ( default : markdown )\n");
        print("\t-n[name] ( API Name header )\n");
        print("\t-h ( show help )\n");
    }

    function parseClassFile($request, $class, $func, $variablesInPath){
        $src_file = fopen("../application/code/".trim(str_replace('\\', '/', $class)).'.php', "r");
        if ($src_file){
            $inc = 0; // счетчик открывающих и закрывающих скобок
            $issetBool = 0; // отметка о том, что мы нашли нужную нам функцию
            $postNameVariable = ""; // название массива переменной
            $params = []; // массив параметров
            while(($line = fgets($src_file)) !== false){

                preg_match("/(function)\s+(.*)\(/", $line, $route_data);
                if ($route_data) { 
                    $function = $route_data[2];
                    if (trim($function) == trim($func)) {
                        $inc = 0;
                        $issetBool = 1;
                    } else {
                        $issetBool = 0;
                    }
                }
                if ($issetBool == 1) {

                    if ($postNameVariable == "") {
                        // поиск названия переменной, которая чекает post параметры
                        preg_match("/(.*)=(.*)->(getParsedBody)/", $line, $parse_data);
                        if ($parse_data) {
                            $postNameVariable = '/(\\'.trim($parse_data[1]).")\['(\w+)'/";
                            $postNameVariable = trim(str_replace("//", "",$postNameVariable));
                            $postNameVariable = str_replace("{","[",$postNameVariable);
                            $postNameVariable = str_replace("}","]",$postNameVariable);
                            var_dump($postNameVariable);
                        }
                    } else {
                        // поиск переменных, которые используют post параметры
                        preg_match($postNameVariable, $line, $parse_data2);
                        if ($parse_data2) {
                            $param = trim($parse_data2[2]);
                            if (!$params[$param]) $params[$param]= $param;
                        }
                    }
                    // чтобы не потеряться в функции инкрементируем если видим "{" и вычитаем если видим "}" если ноль, то конец функции
                    $inc += intval(substr_count($line, "{"));
                    $inc -= intval(mb_substr_count($line, "}"));
                    if ($inc == 0 && $postNameVariable != "") $issetBool == 2;
                }
            }
            
            $return = [];
            if (count($variablesInPath) > 0) {
                foreach ($variablesInPath as $key => $vars) {
                    if (is_string($vars)) {
                        $vars = str_replace("{","",$vars);
                        $vars = str_replace("}","",$vars);
                        $return[] = [
                            "name"          =>  $vars,
                            "in"            =>  "path",
                            "description"   =>  "description",
                            "required"      =>  true,
                            "explode"       =>  true,
                            "schema"        =>  ["type"=>"string"]
                        ];
                    }
                }
            }
            foreach ($params as $key => $p) {
                        $return[] = [
                            "name"          =>  $p,
                            "in"            =>  "query",
                            "description"   =>  "description",
                            "required"      =>  true,
                            "style"         =>  "form",
                            "explode"       =>  true,
                            "schema"        =>  ["type"=>"string"]
                        ];
            }
            return $return;
        }
    }
?>