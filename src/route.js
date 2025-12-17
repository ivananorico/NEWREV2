import Dashboard from './pages/Dashboard'
import Module1 from './pages/module1/Module'
import Submodule1 from './pages/module1/Submodule1'
import Module2 from './pages/Module2'
import GeneralSettings from './pages/settings/General'
import SecuritySettings from './pages/settings/Security'

const routes = [
  {
    path: '/dashboard',
    element: <Dashboard />,
  },
  {
    path: '/module1/submodule1',
    element: <Submodule1 />,
  },
  {
    path: '/module2',
    element: <Module2 />,
  },
  {
    path: '/settings/general',
    element: <GeneralSettings />,
  },
  {
    path: '/settings/security',
    element: <SecuritySettings />,
  },
]

export default routes