<?php

# Etapa 0: Permitindo acesso cross origin
# TICKET [FRONT-001]: Configuração de CORS (Cross-Origin Resource Sharing)
# Permite que o React (porta 5173) converse com o PHP (porta 80)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

# O navegador envia uma requisição "OPTIONS" antes do POST para verificar permissões.
# Se for OPTIONS, nós encerramos com status 200 (OK) sem rodar o resto do código.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

# Etapa 1: Validação do conteúdo e da requisição

# Trocando o tipo de conteudo para JSON
header('Content-Type: application/json');

# Verifica o método http da requisição e mata a requisição caso seja inválida
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    retorna_erro();
}

# Leitura do conteúdo da página
$json_recebido = file_get_contents('php://input');

# Converte o conteúdo recebido para Array associativo
$array_json_recebido = json_decode($json_recebido, true);

# Verifica se o conteúdo recebido é nulo ou se a chave eml_content existe no array
if($array_json_recebido === null || !isset($array_json_recebido['eml_content'])){
    retorna_erro();
}

# Coletando o tamanho da string
$tamanho_string = strlen($array_json_recebido['eml_content']);

# Verificando se a string coletada possui um tamnho não ofensivo para a requisição 
if(!($tamanho_string < 50000 && $tamanho_string > 0)){
    retorna_erro();
}

# Recebendo o conteúdo da chave eml_content no array eml_bruto
$eml_bruto = $array_json_recebido['eml_content'];

#normaliza "pula linha" para ser apenas \n
$eml_bruto = str_replace("\r\n", "\n", $eml_bruto);

# Cortando o EML em cabeçalho e corpo
$partes_do_eml = explode("\n\n", $eml_bruto, 2);

$cabecalho_normalizado = $partes_do_eml[0];

# Solução 1 para remover a dobra de linha (RFC5322) e juntar as linhas quebradas
$cabecalho_desdobrado = preg_replace("/\n[ \t]+/", " ", $cabecalho_normalizado);
 
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
$partes_do_cabecalho = explode("\n", $cabecalho_desdobrado);

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
    // Limpando os dados da linha
    //for($contador_coluna; $contador_coluna < count($cabecalho_final[$contador_linha]); $contador_coluna++){
      //  $cabecalho_final[$contador_linha][$contador_coluna] = trim($cabecalho_final[$contador_linha][$contador_coluna]);
    //}
}


#echo $cabecalho_final['Authentication-Results'] . "\n";

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
for($contador_linha; $contador_linha < count($cabecalho_final); $contador_linha++){
    $contador_coluna = 0;
    for($contador_coluna; $contador_coluna < count($cabecalho_final[$contador_linha]); $contador_coluna++){
        if (preg_match('/\bmultipart/i', $cabecalho_final[$contador_linha][$contador_coluna], $matches)) {
            if(preg_match('/\bboundary=(?:"([^"]+)"|([^;]+))/i', $cabecalho_final[$contador_linha][$contador_coluna], $matches)){
                $boundary = $matches[1];
                $versoes_mensagem = explode("--" . $boundary, $partes_do_eml[1]);
            }
        }
    }
}

# ==============================================================================
/* Funcionava
if (preg_match('/\bmultipart/i', $cabecalho_final['Content-Type'])) {
    if(preg_match('/\bboundary=(?:"([^"]+)"|([^;]+))/i', $cabecalho_final['Content-Type'], $matches)){
        $boundary = $matches[1];
        # Essa variável contém o mesmo texto em vários tipos diferentes
        $versoes_mensagem = explode("--" . $boundary, $partes_do_eml[1]);
    }
    //else {
      //  $versoes_mensagem = $partes_do_eml[1];
   // }
}
*/

# O loop abaixo visa extrair o cabeçalho e mensagem do tipo text/html 
# para validar em qual codificação está o conteúdo (base64 ou quoted-printable)
# para então efetuar sua conversão correta e armazenar estes valores

// Inicializando variáveis
$mensagem_final = "";
$contador_linha = 0;

for($contador_linha; $contador_linha < count($versoes_mensagem); $contador_linha++){
    $array_auxiliar = explode("\n\n", $versoes_mensagem[$contador_linha], 2);
    $mini_cabecalho = trim($array_auxiliar[0]);
    $mini_corpo = $array_auxiliar[1] ?? "";

    // Extrai APENAS o Content-Type text/html
    if (str_contains(strtolower($mini_cabecalho), "text/html")) {
        $array_auxiliar = explode("\n", $mini_cabecalho);

        // Faz a decodificação baseado em quoted-printable ou base64
        if(str_contains(strtolower($array_auxiliar[1]), "quoted-printable")){
            $mensagem_final = trim(quoted_printable_decode($mini_corpo));
        } elseif (str_contains(strtolower($array_auxiliar[1]), "base64")){
            $mensagem_final = base64_decode($mini_corpo);
        }
    }
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
http_response_code(200); 
echo json_encode($resultado_requisicao) . "\n";

function retorna_erro(){
    http_response_code(400);
    //if($mensagem != "") {
     echo json_encode(["status" => "400", "mensagem" => "Erro: Requisição inválida!"]) . "\n";
   // } else {
     //   echo json_encode(["status" => "400", "mensagem" => $mensagem_erro]) . "\n";
   // }
    exit();

}


/*

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