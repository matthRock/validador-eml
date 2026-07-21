import { useState } from 'react'
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
      } catch (erro) {
        setMensagemErro('Erro ao processar a resposta do servidor: ' + erro.message);
      }
    };
    reader.readAsText(event.target.files[0]);
  }

  function handleEditorDidMount(editor, monaco) {
    monaco.editor.setModelMarkers();
  }

  return (
    <>
      <header>
        <h1>Home Page</h1>
      </header>
      <section>
        <div className="editor">
          <input type="file" accept=".eml" onChange={validaUploadArquivo} />
          {mensagemErro && <p className="error">{mensagemErro}</p>}
        </div>
        <Editor
          height="86vh"
          defaultLanguage="ini"
          theme="vs-dark"
          //readOnly={false}
          value={resultadoAnalise && JSON.stringify(resultadoAnalise, null, 2)}
          defaultValue="// Drag and drop a EML file here"
          onMount={handleEditorDidMount}
          options={{
            readOnly: true, // Bloqueia a edição do texto
            domReadOnly: true, // Adiciona um tooltip informando que é somente leitura
            minimap: { enabled: false }
          }}
        />
      </section>
      <footer>
        <p>@2026 All rights reserved</p>
        <p>By Matheus Rocha</p>
      </footer>
    </>
  )
}

export default App