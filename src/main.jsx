import React from 'react'
import ReactDOM from 'react-dom/client'
import { HashRouter } from 'react-router-dom'  // <-- Changed here
import App from './App'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <HashRouter>  {/* <-- Changed here */}
      <App />
    </HashRouter>  {/* <-- Changed here */}
  </React.StrictMode>
)