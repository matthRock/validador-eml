<?php

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

$eml_bruto = $array_json_recebido['eml_content'];
$tamanho_string = strlen($array_json_recebido['eml_content']);

if(!($tamanho_string < 50000 && $tamanho_string > 0)){
    retorna_erro();
}

# Etapa 2: Processamento do EML

# Padronizando o EML para evitar problemas de quebra de linha
$eml_normalizado = str_replace("\r\n", "\n", $eml_bruto);
    
# Cortando o EML em cabeçalho e corpo
$partes_do_eml = explode("\n\n", $eml_normalizado, 2);

# Cortando o cabeçalho do EML para dentro do array
$partes_do_cabecalho = explode("\n", $partes_do_eml[0]);

$resultado_requisicao = [
    "status" => "200",
    "mensagem" => "Motor PHP e Apache operantes!",
    "cabecalho_eml" => $partes_do_cabecalho,
    "corpo_eml" => $partes_do_eml[1] ?? "" # retorna vazio caso o corpo do EML não exista
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

