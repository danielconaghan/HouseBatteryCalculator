import { BrowserRouter, Route, Routes } from 'react-router-dom'

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Feature routes will be registered here */}
        <Route path="/" element={<div>Solar Energy Dashboard</div>} />
      </Routes>
    </BrowserRouter>
  )
}
