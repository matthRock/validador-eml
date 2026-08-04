import { useState } from 'react'
import { useRef } from 'react'
import './App.css'
import Editor from '@monaco-editor/react'
import DomPurify from 'dompurify'

// Toda função no react é um componente,
// Essas funções devem começar com letra maiúscula
// Para que o react consiga diferenciar componetes de funções normais

function EmlPagina({ onEditorMount }) {
  return (
    <Editor
      height="77vh"
      idName="monaco-editor"
      defaultLanguage="json"
      theme="vs-dark"
      defaultValue="// Drag and drop a EML file here"
      onMount={onEditorMount}
      options={{
        readOnly: true,
        domReadOnly: true,
        minimap: { enabled: false }
      }}
    />
  );
}

function MensagemPagina({ conteudoHtml }) {
  return (
    <iframe
      className="editor iframe"
      sandbox=""
      srcDoc={conteudoHtml}
    />
  );
}

function App() {

  // Guarda mensagem de erro
  const [mensagemErro, setMensagemErro] = useState('');
  // Guarda o resultado da análise
  const [resultadoAnalise, setResultadoAnalise] = useState('');
  // Guarda a página atual
  const [currentPage, setCurrentPage] = useState('emlPagina');

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

  // Função que sanitiza o HTML que será exibido no iframe, para evitar ataques Cross Site Scripting (XSS)
  function safeHtmlComponent(dirtyHtmlString) {
    const cleanHtml = DomPurify.sanitize(dirtyHtmlString);
    return cleanHtml;
  }

  // Função chamada quando o editor do Monaco é montado para atualizar conteúdo do editor com o arquivo carregado
  function handleEditorDidMount(editor, monaco) {
    editorRef.current = editor;
  }

  //    const renderPage = () => {
  //   switch (currentPage) {
  //    case 'emlPagina':
  //     return <EmlPagina onEditorMount={handleEditorDidMount} />;
  //  case 'mensagemPagina':
  //   return (
  //    <MensagemPagina
  //     conteudoHtml={resultadoAnalise ? safeHtmlComponent(resultadoAnalise.corpo_eml) : "sem conteúdo recebido"}
  //  />
  // );
  //default:
  //  return <EmlPagina onEditorMount={handleEditorDidMount} />;
  // }
  //};
  //        {/* 5. Display the rendered component */}
  //     <main /*style={{ padding: '20px' }}*/>
  //      {renderPage()}
  //   </main>

  return (
    <>
      <header>
        <h1> EML Analyser</h1>
      </header>
      <nav className="menu">
        <div className="menu-item" onClick={() => setCurrentPage('emlPagina')}>EML</div>
        <div className="menu-item" onClick={() => setCurrentPage('mensagemPagina')}> Mensagem</div>
      </nav>
      <section>
        <div className="editor">
          <input type="file" accept=".eml" onChange={validaUploadArquivo} />
          {mensagemErro && <p className="error">{mensagemErro}</p>}
        </div>

        <div className="editor">
          <div style={{ display: currentPage === 'emlPagina' ? 'block' : 'none' }}>
            <EmlPagina onEditorMount={handleEditorDidMount} />
          </div>
          <div style={{ display: currentPage === 'mensagemPagina' ? 'block' : 'none' }}>
            <MensagemPagina conteudoHtml={resultadoAnalise ? safeHtmlComponent(resultadoAnalise.corpo_eml) : "sem conteúdo recebido"} />
          </div>
        </div>
      </section>
      <footer>
        <p>@2026 All rights reserved | by <a target="_blank" rel="author license noopener noreferrer" href="https://github.com/matthRock">matthRock</a></p>
      </footer>
    </>
  )
}

export default App