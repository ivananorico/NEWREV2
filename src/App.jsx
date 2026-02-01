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
import RPTDashboard from './pages/RPT/RPTDashboard/RPTDashboard'
import RPTDelinquent from './pages/RPT/RPTDelinquent/RPTDelinquent'

// BUSINESS Pages
import BusinessTaxConfig from './pages/BUSINESS/BusinessTaxConfig/BusinessTaxConfig'
import BusinessValidation from './pages/BUSINESS/BusinessValidation/BusinessValidation'
import BusinessValidationInfo from './pages/BUSINESS/BusinessValidation/BusinessValidationInfo'
import BusinessStatus from './pages/BUSINESS/BusinessStatus/BusinessStatus'
import BusinessStatusInfo from './pages/BUSINESS/BusinessStatus/BusinessStatusInfo'
import BusinessTaxDashboard from './pages/BUSINESS/BusinessTaxDashboard/BusinessTaxDashboard'
import BusinessDelinquent from './pages/BUSINESS/BusinessDelinquent/BusinessDelinquent'
// DIGIPAY Pages
import DigiDashboard from './pages/DIGITAL/DigiDashboard/DigiDashoard'


// TREASURY Pages
import RevenueCollection from './pages/TREASURY/RevenueCollection/RevenueCollection'
import Anomaly from './pages/TREASURY/Anomaly/Anomaly'



// MARKET Pages
import MapCreator from './pages/MARKET/MapCreator/MapCreator'
import MarketOutput from './pages/MARKET/MapCreator/MarketOutput'
import ViewAllMaps from './pages/MARKET/MapCreator/ViewAllMaps'
import MapEditor from './pages/MARKET/MapCreator/MapEditor'
import MarketConfig from './pages/MARKET/MapCreator/MarketConfig'
import MarketStatus from './pages/MARKET/MarketStatus/MarketStatus'
import MarketStatusInfo from './pages/MARKET/MarketStatus/MarketStatusInfo'
import MarketDashboard from './pages/MARKET/MarketDashboard/MarketDashboard'
import MarketDelinquent from './pages/MARKET/MarketDelinquent/MarketDelinquent'

import MarketValidation from './pages/MARKET/MarketValidation/MarketValidation'
import MarketValidationInfo from './pages/MARKET/MarketValidation/MarketValidationInfo' // ADD THIS IMPORT

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
              <Route path="/rpt/rptdashboard" element={<RPTDashboard />} />
              <Route path="/rpt/rptdelinquent" element={<RPTDelinquent />} />

              {/* BUSINESS */}
              <Route path="/business/businesstaxdashboard" element={<BusinessTaxDashboard />} />
              <Route path="/business/businesstaxconfig" element={<BusinessTaxConfig />} />
              <Route path="/business/businessvalidation" element={<BusinessValidation />} />
              <Route path="/business/businessvalidationinfo/:id" element={<BusinessValidationInfo />} />
              <Route path="/business/businessstatus" element={<BusinessStatus />} />
              <Route path="/business/businessstatusinfo/:id" element={<BusinessStatusInfo />} />
              <Route path="/business/businessdelinquent" element={<BusinessDelinquent />} />

              {/* TREASURY */}
              <Route path="/treasury/revenuecollection" element={<RevenueCollection/>} />
              <Route path="/treasury/anomaly" element={<Anomaly />} />

              {/* DIGIPAY */}
              <Route path="/digital/digidashboard" element={<DigiDashboard />} />

              {/* MARKET */}
              <Route path="/market/marketdashboard" element={<MarketDashboard />} />
              <Route path="/market/mapcreator" element={<MapCreator />} />
              <Route path="/market/marketoutput/view/:id" element={<MarketOutput />} />
              <Route path="/market/viewallmaps" element={<ViewAllMaps />} />
              <Route path="/market/mapeditor/:id" element={<MapEditor />} />
              <Route path="/Market/Config" element={<MarketConfig />} />
              <Route path="/market/marketdelinquent" element={<MarketDelinquent />} />

              <Route path="/market/marketvalidation" element={<MarketValidation />} />
              <Route path="/market/marketvalidationinfo/:id" element={<MarketValidationInfo />} />
              <Route path="/market/marketstatus" element={<MarketStatus />} />
              <Route path="/market/marketstatusinfo/:id" element={<MarketStatusInfo />} />

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