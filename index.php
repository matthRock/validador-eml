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


# Etapa 2: Processamento do EML

# Cortando o EML em cabeçalho e corpo
$partes_do_eml = explode("\n\n", $eml_bruto, 2);

# Padronizando o EML para evitar problemas de quebra de linha
$cabecalho_normalizado = str_replace("\r\n", "\n", $partes_do_eml[0]);

# Solução 1 para remover a dobra de linha (RFC5322) e juntar as linhas quebradas
$cabecalho_desdobrado = preg_replace("/\n[ \t]+/", " ", $cabecalho_normalizado);

# Cortando o cabeçalho do EML para dentro do array
$partes_do_cabecalho = explode("\n", $cabecalho_desdobrado);

# Recebendo dados para o cabeçalho em formato de array associativo 
$cabecalho_final = cria_dicionario_dados($partes_do_cabecalho);

#echo $cabecalho_final['Authentication-Results'] . "\n";


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

$boundary = "";
if (preg_match('/"([^"]*)"/', $cabecalho_final['Content-Type'], $matches)) {
    $boundary = $matches[1];
}

# Essa variável contém o mesmo texto em vários tipos diferentes
$versoes_mensagem = explode("--" . $boundary, $partes_do_eml[1]);

# O loop abaixo visa extrair o cabeçalho e mensagem do tipo text/html 
# para validar em qual codificação está o conteúdo (base64 ou quoted-printable)
# para então efetuar sua conversão correta e armazenar estes valores
$mensagem_final = "";
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

$resultado_requisicao = [
    "status" => "200",
    "cabecalho_eml" => $cabecalho_final,
    "corpo_eml" => $mensagem_final ?? "", # retorna vazio caso o corpo do EML não exista
    "boundary" => $boundary,
    "auditoria" => $auditoria
];

# Exibindo o resultado bem sucedido da requisição
# e informando o status code 200 (OK) para o cliente
http_response_code(200); 
echo json_encode($resultado_requisicao) . "\n";

function retorna_erro(){
    http_response_code(400);
    echo json_encode(["status" => "400", "mensagem" => "Erro: Requisição inválida!"]) . "\n";
    exit();

}

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