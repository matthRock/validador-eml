import './App.css'

function App() {


  return (
    <>
      <header>
        <h1>Home Page</h1>
      </header>
      <section>
        <div className="editor">
          <input type="file" accept=".eml" className="arquivo_eml"/>
        </div>
        <iframe>
          <p>Aqui será exido o restulado da requisição</p>
        </iframe>
      </section>
      <footer>
        <p>@2026 All rights reserved</p>
        <p>By Matheus Rocha</p>
      </footer>
    </>
  )

}
export default App