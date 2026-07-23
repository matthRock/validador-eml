import { useState } from 'react'
import { useRef } from 'react'
import './App.css'
import Editor from '@monaco-editor/react'

// Toda função no react é um componente,
// Essas funções devem começar com letra maiúscula
// Para que o react consiga diferenciar componetes de funções normais
function App() {

  // Guarda mensagem de erro
  const [mensagemErro, setMensagemErro] = useState('');
  // Guarda o resultado da análise
  const [resultadoAnalise, setResultadoAnalise] = useState('');

  // Inicializando a referencia para o editor do Monaco
  const editorRef = useRef(null);

  //informando o endereço da API PHP
  const url = import.meta.env.VITE_API_URL;


  async function validaUploadArquivo(event) {

    // Carregando o primeiro arquivo via upload
    //const arquivo = event.target.files[0];

    // Criando um objeto leitor de arquivos
    const reader = new FileReader();
    // var conteudoFinal = new object();
    //

    reader.onload = async (eventoDeCarregamento) => {
      const conteudoDoArquivo = eventoDeCarregamento.target.result;

      //const conteudoFinal.eml_content = conteudoDoArquivo;
      try {
        const resposta = await fetch(url, {
          method: 'POST',
          headers:
          {
            'Content-Type': 'application/json',
            // authentication: 'Bearer ' + token;
          },
          body: JSON.stringify({ eml_content: conteudoDoArquivo }),
        });
        setResultadoAnalise(await resposta.json());
        //carrega o arquivo no editor do Monaco
        if (editorRef.current) {
          editorRef.current.setValue(conteudoDoArquivo);
        }
      } catch (erro) {
        setMensagemErro('Erro ao processar a resposta do servidor: ' + erro.message);
      }
    };
    reader.readAsText(event.target.files[0]);
  }

  function handleEditorDidMount(editor, monaco) {
    editorRef.current = editor;
  }

  return (
    <>
      <header>
        <h1> EML Analyser</h1>
      </header>
      <section>
        <div className="editor">
          <input type="file" accept=".eml" onChange={validaUploadArquivo} />
          {mensagemErro && <p className="error">{mensagemErro}</p>}
        </div>
        <div className="editor">
          <Editor
            height="80vh"
            idName="monaco-editor"
            defaultLanguage="json"
            theme="vs-dark"
            defaultValue="// Drag and drop a EML file here"
            onMount={handleEditorDidMount}
            options={{
              readOnly: true, // Bloqueia a edição do texto
              domReadOnly: true, // Adiciona um tooltip informando que é somente leitura
              minimap: { enabled: false }
            }}
          />
        </div>
      </section>
      <footer>
        <p>@2026 All rights reserved | by matthRock</p>
      </footer>
    </>
  )
}

export default App