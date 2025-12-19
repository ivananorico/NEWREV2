import { useState } from 'react'
import { Routes, Route, useLocation } from 'react-router-dom'
import Sidebar from './components/sidebar/sidebar'
import Header from './components/header/Header'
import sidebarItems from './components/sidebar/sidebarItems'

// Pages
import Dashboard from './pages/Dashboard'
import GeneralSettings from './pages/settings/General'
import SecuritySettings from './pages/settings/Security'

// RPT Pages
import RPTConfig from './pages/RPT/RPTConfig/RPTConfig'
import RPTValidationTable from './pages/RPT/RPTValidationTable/RPTValidationTable'
import RPTValidationInfo from './pages/RPT/RPTValidationTable/RPTValidationInfo'
import RPTStatus from './pages/RPT/RPTStatus/RPTStatus'
import RPTStatusInfo from './pages/RPT/RPTStatus/RPTStatusInfo'
import RPTCharts from './pages/RPT/RPTCharts/RPTCharts'
import RPTDashboard from './pages/RPT/RPTDashboard/RPTDashboard'

// BUSINESS Pages
import BusinessTaxConfig from './pages/BUSINESS/BusinessTaxConfig/BusinessTaxConfig'
import BusinessValidation from './pages/BUSINESS/BusinessValidation/BusinessValidation'
import BusinessValidationInfo from './pages/BUSINESS/BusinessValidation/BusinessValidationInfo'
// DIGIPAY Pages
import DIGIPAY1 from './pages/DIGIPAY/DIGIPAY1/DIGIPAY1'
import DIGIPAY2 from './pages/DIGIPAY/DIGIPAY2/DIGIPAY2'

// TREASURY Pages
import TREASURY1 from './pages/TREASURY/TREASURY1/TREASURY1'
import TREASURY2 from './pages/TREASURY/TREASURY2/TREASURY2'

// MARKET Pages
import MapCreator from './pages/MARKET/MapCreator/MapCreator'
import MarketOutput from './pages/MARKET/MapCreator/MarketOutput'
import ViewAllMaps from './pages/MARKET/MapCreator/ViewAllMaps'
import MapEditor from './pages/MARKET/MapCreator/MapEditor'
import MarketConfig from './pages/MARKET/MapCreator/MarketConfig'

function App() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const location = useLocation()

  // Breadcrumb helper
  function getBreadcrumb() {
    for (const item of sidebarItems) {
      if (item.path === location.pathname) return [item.label]
      if (item.subItems) {
        const sub = item.subItems.find(sub => sub.path === location.pathname)
        if (sub) return [item.label, sub.label]
      }
    }
    return ['Dashboard']
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 dark:from-slate-800 dark:via-slate-800 dark:to-slate-800 transition-colors duration-200">
      <div className='flex h-screen overflow-hidden'>
        <Sidebar collapsed={sidebarCollapsed} />
        <div className='flex-1 flex flex-col'>
          <Header
            sidebarCollapsed={sidebarCollapsed}
            onToggleSidebar={() => setSidebarCollapsed(!sidebarCollapsed)}
            breadcrumb={getBreadcrumb()}
          />
          <main className="flex-1 overflow-auto p-8 dark:bg-slate-800">
            <Routes>
              {/* Dashboard */}
              <Route path="/dashboard" element={<Dashboard />} />

              {/* RPT */}
              <Route path="/rpt/rptconfig" element={<RPTConfig />} />
              <Route path="/rpt/rptvalidationtable" element={<RPTValidationTable />} />
              <Route path="/rpt/rptvalidationinfo/:id" element={<RPTValidationInfo />} />
              <Route path="/rpt/rptstatus" element={<RPTStatus />} />
              <Route path="/rpt/rptstatusinfo/:id" element={<RPTStatusInfo />} />
              <Route path="/rpt/rptcharts" element={<RPTCharts />} />
              <Route path="/rpt/rptdashboard" element={<RPTDashboard />} />

              {/* BUSINESS */}
              <Route path="/business/businesstaxconfig" element={<BusinessTaxConfig />} />
              <Route path="/business/businessvalidation" element={<BusinessValidation />} />
              <Route path="/business/businessvalidationinfo/:id" element={<BusinessValidationInfo />} />

              {/* TREASURY */}
              <Route path="/treasury/treasury1" element={<TREASURY1 />} />
              <Route path="/treasury/treasury2" element={<TREASURY2 />} />

              {/* DIGIPAY */}
              <Route path="/digipay/digipay1" element={<DIGIPAY1 />} />
              <Route path="/digipay/digipay2" element={<DIGIPAY2 />} />

              {/* MARKET */}
              <Route path="/market/mapcreator" element={<MapCreator />} />
              <Route path="/market/marketoutput/view/:id" element={<MarketOutput />} />
              <Route path="/market/viewallmaps" element={<ViewAllMaps />} />
              <Route path="/market/mapeditor/:id" element={<MapEditor />} />
              <Route path="/Market/Config" element={<MarketConfig />} />

              {/* Settings */}
              <Route path="/settings/general" element={<GeneralSettings />} />
              <Route path="/settings/security" element={<SecuritySettings />} />
            </Routes>
          </main>
        </div>
      </div>
    </div>
  )
}

export default App