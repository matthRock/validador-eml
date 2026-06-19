<?php

# Trocando o tipo de conteudo para JSON
header('Content-Type: application/json');

# Recebe o método http da requisição
$metodo_http = $_SERVER['REQUEST_METHOD'];

#### Inicialização de variáveis ############

# Array associativo que vai conter o resultado da requisição
$resultado_request = [ 'status' => '', 'Mensagem' => '' ];


if(validador_requisicao($metodo_http)){
    cria_resposta();
}

function cria_resposta(){
    if (verifica_payload()){
        # Formula a resposta para o cliente
        $resultado_request["status"] = '200';
        $resultado_request["Mensagem"] = "Motor PHP e Apache operantes!";
        # Exibindo o resultado convertido para JSON
        echo json_encode($resultado_request);
        echo "\n";
    } else {
        # Exibindo mensagem de erro para o Cliente
        $resultado_request["status"] = '400';
        $resultado_request["Mensagem"] = "Erro: O conteúdo inválido!/n";
        echo json_encode($resultado_request);
        echo "\n";
    }
}

function validador_requisicao($metodo_http){
    # Inicializando a variável que 
    # vai verificar se o método http é válido 

    $metodo_valido = false;

    # Verifica se o método http é válido
    switch ($metodo_http) {
        case 'POST':
            $metodo_valido = true;
            break;
        default:
            $metodo_valido = false;
    }
    return $metodo_valido;
}

function verifica_payload(){

    # String para validar o payload recebido 
    # (conteúdo > 1000 caracteres e < 0 caracteres)
    $string_validada = false;

    # Recebe o conteúdo da requisição em formato json
    $json_recebido = file_get_contents('php://input');
    
    # Converte o conteúdo recebido para Array associativo
    $array_json_recebido = json_decode($json_recebido, true);

    #conta a quantidade de strings no array associativo
    $tamanho_string = strlen($array_json_recebido['eml_content']);

    # Verifica se o conteúdo recebido é válido  
    if($tamanho_string < 1000 && $tamanho_string > 0){
        $string_validada = true;
    }
    return $string_validada;
}

?>
