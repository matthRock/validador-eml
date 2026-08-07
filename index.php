<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

# Etapa 0: Permitindo acesso cross origin
# Permite que o React (porta 5173) converse com o PHP (porta 80)
# No momento ainda não implementei uma verificação de origem, então qualquer site pode acessar a API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

# O navegador envia uma requisição "OPTIONS" antes do POST para verificar permissões.
# Se for OPTIONS, nós encerramos com status 200 (OK) sem rodar o resto do código.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

# Trocando o tipo de conteudo para JSON
header('Content-Type: application/json');

# Verifica o método http da requisição e mata a requisição caso seja inválida
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    retorna_erro();
}


// Nova lógica para receber EMLs grandes, como os corporativos, que podem ter mais de 50.000 caracteres e anexos complexos
if(!isset($_FILES['arquivo_eml'])){
    //retorna_erro();
    http_response_code(400);
    echo json_encode(["status" => "400", "mensagem" => "A variavel global FILES esta vazia. O FormData nao chegou."]);
    exit();
}

# Verifica se ocorreu erro no upload
if($_FILES['arquivo_eml']['error'] !== UPLOAD_ERR_OK){
    //retorna_erro();
    http_response_code(400);
    echo json_encode([
        "status" => "400", 
        "mensagem" => "Erro na engenharia de transporte do servidor (Codigo: $erro_upload)"
    ]);
    exit();
}

# Criação de ponteiro para o arquivo EML dentro de /tmp do php, o r é o modo somente leitura, antes ocorria a leitura direta do arquivo todo
$eml_bruto = fopen($_FILES['arquivo_eml']['tmp_name'], 'r');

# Verifica se ocorreu erro ao abrir ou localizar o arquivo
if($eml_bruto === false){
    http_response_code(500);
    echo json_encode(["status" => "500", "mensagem" => "Falha fatal: O PHP nao conseguiu abrir o arquivo armazenado em /tmp"]);
    exit();
}

# $partes_do_eml = [0 => "", 1 => ""];

#Armazena o ponteiro da linha atual do arquivo
$linha_atual = "";
# Array do cabeçalho
$partes_do_cabecalho = [];
# Contador linha
$linha = 0;
# String com o corpo da mensagem
$partes_mensagem = "";
# Interruptor de leitura arquivo ou corpo
$contador_arquivo = 0;

# Este loop tem a função de ler o arquivo e gerar o array com o conteúdo EML, nada além disso
while(!feof($eml_bruto)){
    // Normaliza o pula linha 
    $linha_atual = str_replace("\r\n", "\n", fgets($eml_bruto));
    // Verifica se a linha está vazia para mudar o array 
    if($contador_arquivo === 0){
        if(trim($linha_atual, "\n") === ""){
            $contador_arquivo++;
            $linha = 0;
        } else {
         // Adicionar o conteúdo da linha no indice atual contatenando
         // Normalizando o "pula linha" para ser apenas \n
            if(preg_match("/^[ \t]+/", $linha_atual, $matches)){
                //$linha_atual = preg_replace("/\n[ \t]+/", " ", $linha_atual);
                $partes_do_cabecalho[$linha-1] .= rtrim($linha_atual, "\n");
            } else {
                //$linha_atual = preg_replace("/\n[ \t]+/", " ", $linha_atual);
                $partes_do_cabecalho[$linha] = rtrim($linha_atual, "\n");
                $linha++;
            }
        }
    } else {
        //$linha_atual = preg_replace("/\n[ \t]+/", " ", $linha_atual);
        $partes_mensagem .= $linha_atual;
    }
}
// Finaliza o ponteiro liberando memória
fclose($eml_bruto);

//var_dump($partes_do_cabecalho);
//echo json_encode($partes_cabecalho) . "\n";
//echo json_encode($partes_mensagem) . "\n";
//exit();
//var_dump($partes_mensagem);


# normaliza "pula linha" para ser apenas \n
// Feito no fopen
// $eml_bruto = str_replace("\r\n", "\n", $eml_bruto);

# Cortando o EML em cabeçalho e corp
// feito no fopen
//$partes_do_eml = explode("\n\n", $eml_bruto, 2);

//$cabecalho_normalizado = $partes_do_eml[0];

# Solução 1 para remover a dobra de linha (RFC5322) e juntar as linhas quebradas
//$cabecalho_desdobrado = preg_replace("/\n[ \t]+/", " ", $cabecalho_normalizado);
 
# Cortando o cabeçalho do EML para dentro do array
# Não pode ser um explode, precisa criar um Array Multidimensional

/*
#=============================================================================================================================
# original que "está dando certo"
#$partes_do_cabecalho = explode("\n", $cabecalho_desdobrado);
#Indice da Linha
$linhaCabecalho = 0;
#Indice do conteúdo da linha
$subLinhaCabecalho = 0;
#Referencia para o for array[20]
$conteudoLinhasCabecalhoRef = explode("\n", $cabecalho_desdobrado);
# Array multidimensional contendo o cabeçalho
$partes_do_cabecalho = [];

for($linhaCabecalho; $linhaCabecalho < count($conteudoLinhasCabecalhoRef); $linhaCabecalho++){
    
}
*/

# Inserindo cada linha do cabeçalho em uma posição do array
//$partes_do_cabecalho = explode("\n", $cabecalho_desdobrado);

# Recebendo dados para o cabeçalho em formato de array associativo 
# $cabecalho_final = cria_dicionario_dados($partes_do_cabecalho);

// Array que vai receber a versão FInal do cabeçalho
$cabecalho_final = [];
$contador_linha = 0;

// Tornando o EML um array bidimensional
for($contador_linha; $contador_linha < count($partes_do_cabecalho); $contador_linha++){
// Explodindo a linha em chave valor 
    $contador_coluna = 0;
    $cabecalho_final[$contador_linha] = explode(":", $partes_do_cabecalho[$contador_linha], 2);
}

// Validação da autenticação do Envio (SPF, Dmarc e DKIM)
$validador = [ 
    'spf' => "/\bspf=([a-zA-Z]+)/i",
    'dmarc' => "/\bdmarc=([a-zA-Z]+)/i",
    'dkim' => "/\bdkim=([a-zA-Z]+)/i"
    ];

$auditoria = [ 'spf' => '', 'dmarc' => '', 'dkim' => ''];

$resultado_autenticacao = $cabecalho_final['Authentication-Results'] ?? "";

foreach($validador as $key => $value){
    if (preg_match($value, $resultado_autenticacao, $matches)){
        $auditoria[$key] = trim($matches[1]) ?? "";
        #echo "O " . $key . " = " . $auditoria[$key] . "\n";
    }
}

// Inicializando variáveis
$matches = []; 
$boundary = ""; // boundary que separa os tipos da mesma mensagem
$versoes_mensagem = ""; // array com as versões da mensagem
$contador_linha = 0;

// Valida o Content-Type e extrai o Boundary
/*
for($contador_linha; $contador_linha < count($cabecalho_final); $contador_linha++){
    $contador_coluna = 0;
    for($contador_coluna; $contador_coluna < count($cabecalho_final[$contador_linha]); $contador_coluna++){
        if (preg_match('/\bmultipart/i', $cabecalho_final[$contador_linha][$contador_coluna], $matches)) {
            if(preg_match('/\bboundary=(?:"([^"]+)"|([^;]+))/i', $cabecalho_final[$contador_linha][$contador_coluna], $matches)){
                #Previne que boundary esteja vazio
                $boundary = !empty($matches[1]) ? $matches[1] : $matches[2];
                $versoes_mensagem = explode("--" . $boundary, $partes_mensagem);
                
            }
        }
    }
}
*/

# Analisa a string e divide ela em todos os boundarys que localizar
$versoes_mensagem = preg_split('/^--[A-Za-z0-9=_.-]+$/m', $partes_mensagem);

#echo "Boundary: " . $boundary . "\n";
#echo "Versões da mensagem: \n";
#var_dump($versoes_mensagem);
#exit();

# O loop abaixo visa extrair o cabeçalho e mensagem do tipo text/html 
# para validar em qual codificação está o conteúdo (base64 ou quoted-printable)
# para então efetuar sua conversão correta e armazenar estes valores

// Inicializando variáveis
$mensagem_final = "";
$contador_linha = 0;
$mensagem_html = "";
$mensagem_texto = "";
$limpa_mensagem = "";

for($contador_linha; $contador_linha < count($versoes_mensagem); $contador_linha++){

// Se não houver mini_corpo (a fatia não tinha um \n\n), também pula.
    if(preg_match('/Content-Type/', $versoes_mensagem[$contador_linha])){
        
        $limpa_mensagem = ltrim($versoes_mensagem[$contador_linha]);

        if ($limpa_mensagem === "") continue; // Caso não tenha mensagem, pula
        
        $array_auxiliar = explode("\n\n", $limpa_mensagem, 2);
        $mini_cabecalho = $array_auxiliar[0];
        $mini_corpo = $array_auxiliar[1] ?? "";

        if ($mini_corpo === "") continue; // caso não tenha corpo, pula

        // Extrai APENAS o Content-Type text/html
        if (str_contains(strtolower($mini_cabecalho), "text/plain")) {
            # Faz a decodificação em quoted-printable
            if(str_contains(strtolower($mini_cabecalho), "quoted-printable")){
                $mensagem_texto = quoted_printable_decode($mini_corpo);
            # Faz a decodificação em base64
            } elseif (str_contains(strtolower($mini_cabecalho), "base64")){
                $mensagem_texto = base64_decode($mini_corpo);
            }
            // Faz a decodificação basead em7bit, 8bit ou binary
            elseif (str_contains(strtolower($mini_cabecalho), "7bit") || str_contains(strtolower($mini_cabecalho), "binary")){
                $mensagem_texto = $mini_corpo;
            } elseif(str_contains(strtolower($mini_cabecalho), "8bit")){
                if(preg_match('/charset="?([A-Za-z0-9-]+)"?/i', $mini_cabecalho, $matches)){
                    if($matches[1] === "UTF-8"){
                        $mensagem_texto = strtoupper($mini_corpo);
                    } else {
                        $mensagem_texto = mb_convert_encoding($mini_corpo, "UTF-8", $matches[1]);
                    }
                }
            }
        # Extrai a mensagem HTML 
        } elseif (str_contains(strtolower($mini_cabecalho), "text/html")) {
            # Faz a decodificação em quoted-printable
            if(str_contains(strtolower($mini_cabecalho), "quoted-printable")){
                $mensagem_html = quoted_printable_decode($mini_corpo);
            # Faz a decodificação em base64
            } elseif (str_contains(strtolower($mini_cabecalho), "base64")){
                $mensagem_html = base64_decode($mini_corpo);
            }
            // Faz a decodificação basead em7bit, 8bit ou binary
            elseif (str_contains(strtolower($mini_cabecalho), "7bit") || str_contains(strtolower($mini_cabecalho), "binary")){
                $mensagem_html = $mini_corpo;
            } elseif (str_contains(strtolower($mini_cabecalho), "8bit")){
                if(preg_match('/charset="?([A-Za-z0-9-]+)"?/i', $mini_cabecalho, $matches)){
                    if($matches[1] === "UTF-8"){
                        $mensagem_html = trim($mini_corpo);
                    } else {
                        $mensagem_html = mb_convert_encoding($mini_corpo, "UTF-8", $matches[1]);
                    }
                }
            }
        }
    }
    if($mensagem_html !== ""){
        break;
    }
}

//echo "Array completo \n";
//var_dump($array_auxiliar);
#echo "cabecalho \n";
#var_dump($mini_cabecalho);
#echo "corpo \n";
#var_dump($mini_corpo);
#echo "Versão HTML"
#var_dump($mensagem_html);
#echo "Vesão Texto Simples"
#var_dump($mensagem_texto);
#exit();

if($mensagem_html === ""){
    $mensagem_final = $mensagem_texto;
} else {
    $mensagem_final = $mensagem_html;
}

# ============================================================================
/*
Funcionava
foreach($versoes_mensagem as $value){
    $array_auxiliar = explode("\n\n", $value, 2);
    $mini_cabecalho = trim($array_auxiliar[0]);
    $mini_corpo = $array_auxiliar[1] ?? "";

    if (str_contains(strtolower($mini_cabecalho), "text/html")) {
        $array_auxiliar = explode("\n", $mini_cabecalho);
        
        if(str_contains(strtolower($array_auxiliar[1]), "quoted-printable")){
            $mensagem_final = trim(quoted_printable_decode($mini_corpo));
        } elseif (str_contains(strtolower($array_auxiliar[1]), "base64")){
            $mensagem_final = base64_decode($mini_corpo);
        }
    }
}
*/

# No momento o corpo_eml está sendo retornado apenas se for do tipo text/html, caso contrário será vazio

$resultado_requisicao = [
    "status" => "200",
    "cabecalho_eml" => $cabecalho_final,
    "corpo_eml" => $mensagem_final ?? "" # retorna vazio caso o corpo do EML não exista
    //"boundary" => $boundary ?? ""
    // "auditoria" => $auditoria
];

# Exibindo o resultado bem sucedido da requisição
# e informando o status code 200 (OK) para o cliente
//http_response_code(200); 
//echo json_encode($resultado_requisicao) . "\n";


$json_final = json_encode($resultado_requisicao);

# Se o JSON falhar, a variável será idêntica a false
if ($json_final === false) {
    http_response_code(500);
    $erro_json = json_last_error_msg(); # Pega o erro interno do motor JSON do PHP
    echo json_encode([
        "status" => "500", 
        "mensagem" => "Falha fatal de UTF-8: O PHP nao conseguiu converter o array para JSON. Motivo: " . $erro_json
    ]);
} else {
    http_response_code(200); 
    echo $json_final;
}

function retorna_erro(){
    http_response_code(400);
    //if($mensagem != "") {
     echo json_encode(["status" => "400", "mensagem" => "Erro: Requisição inválida!"]) . "\n";
   // } else {
     //   echo json_encode(["status" => "400", "mensagem" => $mensagem_erro]) . "\n";
   // }
    exit();
}

function decodificaMensagem($array_mensagem, $array_cabecalho){
    $mensagem_saida = "";
    $cabecalho_lower = strtolower($array_cabecalho);

    if (str_contains($cabecalho_lower, "quoted-printable")) {
        $mensagem_saida = trim(quoted_printable_decode($array_corpo));
    } elseif (str_contains($cabecalho_lower, "base64")) {
        $mensagem_saida = base64_decode($array_corpo);
    } elseif (str_contains($cabecalho_lower, "7bit") || str_contains($cabecalho_lower, "binary")) {
        $mensagem_saida = trim($array_corpo);
    } elseif (str_contains($cabecalho_lower, "8bit")) {
        $mensagem_saida = trim($array_corpo);
        if (preg_match('/charset="?([A-Za-z0-9-]+)"?/i', $array_cabecalho, $matches)) {
            $charset = strtoupper($matches[1]);
            if ($charset !== "UTF-8") {
                $mensagem_saida = mb_convert_encoding($mensagem_saida, "UTF-8", $charset);
            }
        }
    } else {
        $mensagem_saida = trim($array_corpo);
    }
    
    return $mensagem_saida;
}
/*

if (str_contains(strtolower($mini_cabecalho), "text/plain")) {
            # Faz a decodificação em quoted-printable
            if(str_contains(strtolower($mini_cabecalho), "quoted-printable")){
                $mensagem_texto = quoted_printable_decode($mini_corpo);
            # Faz a decodificação em base64
            } elseif (str_contains(strtolower($mini_cabecalho), "base64")){
                $mensagem_texto = base64_decode($mini_corpo);
            }
            // Faz a decodificação basead em7bit, 8bit ou binary
            elseif (str_contains(strtolower($mini_cabecalho), "7bit") || str_contains(strtolower($mini_cabecalho), "binary")){
                $mensagem_texto = $mini_corpo;
            } elseif(str_contains(strtolower($mini_cabecalho), "8bit")){
                if(preg_match('/charset="?([A-Za-z0-9-]+)"?/i', $mini_cabecalho, $matches)){
                    if($matches[1] === "UTF-8"){
                        $mensagem_texto = strtoupper($mini_corpo);
                    } else {
                        $mensagem_texto = mb_convert_encoding($mini_corpo, "UTF-8", $matches[1]);
                    }
                }
            }
        # Extrai a mensagem HTML 
        } elseif (str_contains(strtolower($mini_cabecalho), "text/html")) {
            # Faz a decodificação em quoted-printable
            if(str_contains(strtolower($mini_cabecalho), "quoted-printable")){
                $mensagem_html = quoted_printable_decode($mini_corpo);
            # Faz a decodificação em base64
            } elseif (str_contains(strtolower($mini_cabecalho), "base64")){
                $mensagem_html = base64_decode($mini_corpo);
            }
            // Faz a decodificação basead em7bit, 8bit ou binary
            elseif (str_contains(strtolower($mini_cabecalho), "7bit") || str_contains(strtolower($mini_cabecalho), "binary")){
                $mensagem_texto = $mini_corpo;
            } elseif(str_contains(strtolower($mini_cabecalho), "8bit")){
                if(preg_match('/charset="?([A-Za-z0-9-]+)"?/i', $mini_cabecalho, $matches)){
                    if($matches[1] === "UTF-8"){
                        $mensagem_texto = strtoupper($mini_corpo);
                    } else {
                        $mensagem_texto = mb_convert_encoding($mini_corpo, "UTF-8", $matches[1]);
                    }
                }
            }
        }
    }
    if($mensagem_html !== ""){
        break;
    }
=========================================


Referência de pensamento

$pizza = "pizza: 1,pizza: 2,pizza: 3";
$pizza;
$pizza = explode(",", $pizza);
$i = 0;
$teste = [];

for($i; $i < count($pizza); $i++){
    $j = 0;
    $teste[$i] = explode(":", $pizza[$i], 2); 
    for($j; $j < count($teste[$i]); $j++){
        $teste[$i][$j] = trim($teste[$i][$j]);
    }
}

$i = 0;
for($i; $i < count($pizza); $i++){
 $j = 0;
 for($j; $j < count($teste[$i]); $j++){
  echo $teste[$i][$j] . "\n";
 }
}
*/


/*
function cria_dicionario_dados($array_original){
    $array_auxiliar = [];
    contador_linha = 0;
    for($contador_linha; $contador_linhas <= count($array_original); $contador_linha++){
        // Explodindo a linha em chave valor 
        $contador_coluna = 0;
        $array_auxiliar[$contador_linha] = explode(":", trim($array_original[$contador_linha]), 2);
        // Limpando os dados da linha
        for($contador_coluna; contador_coluna < count($array_original[]), $contador_coluna++){
            $array_auxiliar[$contador_linha][$contador_coluna] = trim($array_auxiliar[$contador_linha][$contador_coluna]);
        }
    }
    return $array_auxiliar;
}
*/
/*
O Original era assim:
function cria_dicionario_dados($array_original){
    $array_auxiliar = [];
    foreach($array_original as $value){
    $array_auxiliar = explode(":", $value, 2);
        if(count($array_auxiliar) === 2){
            $array_original[trim($array_auxiliar[0])] = trim($array_auxiliar[1]);
        }
    }
    return $array_original;
}
/*

function rfc5322($cabecalho){
    $countLinha = 0;
    $dominioMessageID = "";
    $dominioFrom= "";
    $validacaoRFC5322 = [];
    $contagemStrings = [];
    $validaCabecalho = [
        'from:' => false,
        'date:' => false,
        'to:' => false,
        'message-id:' => false,
        'subject:' => false,
        'cc:' => false,
        'bcc:' => false,
        'duplicidade' => true,
        'dominioIgual' => false
    ];
    $contagemTotal = 0;
    foreach($cabecalho as $key => $value ){
    $countLinha++;
    // 1- Precisa possuir obrigatoriamente os cabeçalhos Date e From (RFC 5322).
    // 2- Precisa garantir que os cabeçalhos Date, From, Message-ID, Subject, To, Cc e Bcc apareçam apenas uma vez por mensagem (RFC 5322)
        switch(strtolower($key)){
            case "date:":
                $validaCabecalho['date:'] = true;
                $contagemTotal++;
                break;
            case "from:":
                if(existeDominio($cabecalho['from:'])){
                    $validaCabecalho['from:'] = true;
                    $contagemTotal++;
                    $dominioFrom = explode("@", $value, 2);
                }
                break;
            case "to:":
                $validaCabecalho['to:'] = true;
                $contagemTotal++;
                break;
    // 3- Precisa validar se o formato do Message-ID segue a sintaxe estrita <parte-local@dominio> (RFC 5322).
            case "message-id:":
                if(existeDominio($cabecalho['message-id:'])){
                    $validaCabecalho['message-id:'] = true;
                    $dominioMessageID = explode("@", $value, 2) ?? "";
                    $contagemTotal++;
                }
                break;
            case "subject:":
                $validaCabecalho['subject:'] = true;
                $contagemTotal++;
                break;
            case "cc:":
                $validaCabecalho['cc:'] = true;
                $contagemTotal++;
                break;
            case "bcc:":
                $validaCabecalho['bcc:'] = true;
                $contagemTotal++;
                break;
        }
    // 4- Precisa garantir que nenhuma linha bruta do cabeçalho ultrapasse o limite máximo de 998 caracteres (RFC 5322).
        if((strlen($key)+strlen($value)) > 998){
        // Adiciona dentro do array como indice a chave $key contendo os valores número de linhas e quantidade de caractéres da linha
        $contagemStrings[$key] = [$countLinha, (strlen($key)+strlen($value))];
        }
    }

    // Conta quantos trues o cabeçalho recebeu
    $quantidadeParametros = array_count_values($validaCabecalho);

    // Verifica se o domínio do Message-ID bate com o domínio do from
    if(existeDominio($cabecalho['from:'] ?? '') && existeDominio($cabecalho['message-id:'] ?? '')){
        if($dominioFrom[1] === $dominioMessageID[1]){
            $validaCabecalho['dominioIgual'] = true;
        }
    }
    // Verifica se existem duplicidades  e insere no array de validação
    if($contagemTotal <= 7 && $contagemTotal >=2 && $quantidadeParametros['1'] == $contagemTotal){
        $validaCabecalho["duplicidade"] = false;
    }

    // Alimenta o array que será retornado com a validação do cabeçalho 
    $validacaoRFC5322['validacao_campos_cabecalho'] = $validaCabecalho;

    // Alimenta com contagem de caracteres por linha
    $validacaoRFC5322['linhas_fora_do_limite'] = $contagemStrings;

    // Alimenta com a contagem de campos localizado
    $validacaoRFC5322['quantidade_campos_localizados'] = $contagemTotal;
    
    return $validacaoRFC5322;
}

function existeDominio($conta){
    $dominio = false;
    if(preg_match("/^<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>$/", $conta, $matches)){
        $dominio = true;
    }
    return $dominio;
}

/*

ok 1- Precisa possuir obrigatoriamente os cabeçalhos Date e From (RFC 5322).
ok 2- Precisa garantir que os cabeçalhos Date, From, Message-ID, Subject, To, Cc e Bcc apareçam apenas uma vez por mensagem (RFC 5322).
ok 3- Precisa validar se o formato do Message-ID segue a sintaxe estrita <parte-local@dominio> (RFC 5322).
ok 4- Precisa garantir que nenhuma linha bruta do cabeçalho ultrapasse o limite máximo de 998 caracteres (RFC 5322).

5- Precisa possuir o cabeçalho MIME-Version: 1.0 se a mensagem contiver HTML ou anexos (RFC 2045).
6- Precisa possuir a tag boundary= declarada no Content-Type se a mensagem for do tipo multipart (RFC 2045).
7- Precisa garantir que o nome do boundary não ultrapasse 70 caracteres e não termine com espaços vazios (RFC 2045).
8- Precisa validar se textos com acentos/caracteres especiais no cabeçalho estão envelopados na sintaxe exata =?charset?encoding?texto_codificado?= (RFC 2047).
9- Precisa garantir que o parâmetro de codificação (encoding) de textos especiais seja exclusivamente Q (Quoted-Printable) ou B (Base64) (RFC 2047).
10- Precisa possuir as tags estruturais obrigatórias v=, a=, b=, bh=, d=, s= e h= dentro do cabeçalho DKIM-Signature (RFC 6376).
11- Precisa garantir que a tag h= (lista de campos assinados pelo DKIM) inclua obrigatoriamente o cabeçalho From (RFC 6376).
12- Precisa validar se o cabeçalho Authentication-Results possui a estrutura correta de método e resultado, como spf=pass ou dkim=fail (RFC 8601).

*/