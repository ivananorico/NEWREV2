import React from 'react'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { 
  Globe, 
  ChevronDown, 
  Sparkles,
  LayoutDashboard,
  Settings,
  Home,
  Building,
  Briefcase,
  Landmark,
  CreditCard,
  Store,
  BarChart3,
  FileText,
  Shield,
  Database,
  MapPin,
  CheckCircle,
  AlertCircle,
  LogOut
} from 'lucide-react'
import sidebarItems from './sidebarItems'
import ProfileCard from './ProfileCard'

// Environment-based URL configuration
const isProduction = window.location.hostname.includes('goserveph.com');
const LOGOUT_URL = isProduction 
  ? "/index.php"  // Production domain - root path
  : "http://localhost/revenue2/index.php"; // Local development

// Map module IDs to specific icons
const moduleIcons = {
  dashboard: Home,
  module1: Landmark, // Real Property - Landmark/Building icon
  module2: Briefcase, // Business Tax - Briefcase for business
  module4: BarChart3, // Treasury Dashboard - Analytics
  module5: CreditCard, // Digital Payment - Credit card
  module6: Store, // Market Stall - Store icon
  settings: Settings,
}

// Map subitem icons
const subItemIcons = {
  // RPT subitems
  rpt1: LayoutDashboard,
  rpt2: Settings,
  rpt3: CheckCircle,
  rpt4: AlertCircle,
  // Business subitems
  BusinessTaxDashboard: LayoutDashboard,
  BusinessTaxConfig: Settings,
  BusinessValidation: CheckCircle,
  BusinessStatus: AlertCircle,
  // Treasury subitems
  Revenue: BarChart3,
  // Digital subitems
  digidashboard: LayoutDashboard,
  // Market subitems
  market1: LayoutDashboard,
  market2: MapPin, // Map Creator gets MapPin icon
  market3: CheckCircle,
  market4: AlertCircle,
  // Settings subitems
  'general-settings': Settings,
  'security-settings': Shield,
}

function Sidebar({ collapsed }) {
  const location = useLocation()
  const navigate = useNavigate()
  const [expandedItem, setExpandedItem] = React.useState(new Set())
  const [hoveredItem, setHoveredItem] = React.useState(null)

  React.useEffect(() => {
    const newExpanded = new Set()
    sidebarItems.forEach(item => {
      if (item.subItems) {
        const isActiveSubItem = item.subItems.some(
          subItem => location.pathname === subItem.path
        )
        if (isActiveSubItem) {
          newExpanded.add(item.id)
        }
      }
    })
    setExpandedItem(newExpanded)
  }, [location.pathname])

  const toggleExpanded = (item) => {
    const newExpanded = new Set(expandedItem)
    if (newExpanded.has(item.id)) {
      newExpanded.delete(item.id)
    } else {
      newExpanded.add(item.id)
      // If the item has subItems and none are currently active, navigate to the first subitem
      if (item.subItems && item.subItems.length > 0 && !item.subItems.some(sub => sub.path === location.pathname)) {
        navigate(item.subItems[0].path)
      }
    }
    setExpandedItem(newExpanded)
  }

  // Handle logout with environment-based URL
  const handleLogout = () => {
    // Clear any authentication tokens, user data, etc.
    localStorage.removeItem('authToken')
    localStorage.removeItem('userData')
    localStorage.removeItem('userRole')
    sessionStorage.clear()
    
    // Redirect based on environment
    window.location.href = LOGOUT_URL
  }

  // Get module icon with fallback
  const getModuleIcon = (itemId, itemIcon) => {
    if (moduleIcons[itemId]) {
      const IconComponent = moduleIcons[itemId]
      return <IconComponent className="w-5 h-5" />
    }
    if (itemIcon) {
      const IconComponent = itemIcon
      return <IconComponent className="w-5 h-5" />
    }
    return <LayoutDashboard className="w-5 h-5" />
  }

  // Get subitem icon
  const getSubItemIcon = (subItemId) => {
    if (subItemIcons[subItemId]) {
      const IconComponent = subItemIcons[subItemId]
      return <IconComponent className="w-4 h-4" />
    }
    return <FileText className="w-4 h-4" />
  }

  return (
    <div className={`${collapsed ? 'w-20' : 'w-72'} bg-gradient-to-b from-white to-[#fbfbfb] border-r border-slate-100 flex flex-col transition-all duration-300 ease-in-out shadow-sm`}>
      {/* Logo */}
      <div className='p-6 pb-4'>
        <NavLink 
          to="/" 
          className='flex items-center space-x-3 group'
          onMouseEnter={() => setHoveredItem('logo')}
          onMouseLeave={() => setHoveredItem(null)}
        >
          <div className={`w-12 h-12 bg-gradient-to-br from-[#4a90e2] to-[#357ae8] rounded-2xl flex items-center justify-center text-white text-xl font-bold transition-all duration-300 group-hover:scale-105 group-hover:shadow-md ${hoveredItem === 'logo' ? 'ring-2 ring-[#4a90e2]/20' : ''}`}>
            <Globe className='w-7 h-7' />
          </div>
          {!collapsed && (
            <div className='transition-all duration-300'>
              <div className='flex items-center space-x-2'>
                <h1 className='text-2xl font-bold bg-gradient-to-r from-gray-900 to-[#4a90e2] bg-clip-text text-transparent'>GSM</h1>
                <div className='px-2 py-0.5 bg-gradient-to-r from-[#4caf50]/10 to-[#4a90e2]/10 rounded-lg border border-[#4caf50]/20'>
                  <p className='text-xs font-semibold text-[#4caf50] flex items-center'>
                    <Sparkles className='w-3 h-3 mr-1' />
                    PRO
                  </p>
                </div>
              </div>
              <p className='text-xs text-[#9aa5b1] mt-1 font-medium'>Government System Management</p>
            </div>
          )}
        </NavLink>
      </div>

      {/* Divider */}
      <div className='px-6 pb-4'>
        <div className='h-px bg-gradient-to-r from-transparent via-[#9aa5b1]/20 to-transparent'></div>
      </div>

      {/* Navigation Links */}
      <nav className='flex-1 px-3 pb-6 space-y-1 overflow-y-auto'>
        {sidebarItems.map((item) => {
          const isActive = item.path === location.pathname || 
            (item.subItems && item.subItems.some(
              subItem => subItem.path === location.pathname
            ))

          const isExpanded = expandedItem.has(item.id)
          const IconComponent = item.icon

          return (
            <div key={item.id} className='relative'>
              {item.subItems ? (
                <>
                  <button
                    className={`w-full flex justify-between items-center p-3 rounded-2xl transition-all duration-300 group relative overflow-hidden ${
                      isActive
                        ? 'bg-gradient-to-r from-[#4a90e2]/10 to-[#4a90e2]/5 text-[#4a90e2] font-semibold shadow-sm'
                        : 'text-[#64748b] hover:bg-gradient-to-r hover:from-slate-50 hover:to-white hover:text-gray-800 hover:shadow-sm'
                    }`}
                    onClick={() => toggleExpanded(item)}
                    onMouseEnter={() => setHoveredItem(item.id)}
                    onMouseLeave={() => setHoveredItem(null)}
                  >
                    {/* Active indicator */}
                    {isActive && !collapsed && (
                      <div className='absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-gradient-to-b from-[#4a90e2] to-[#357ae8] rounded-r-full'></div>
                    )}
                    
                    <div className='flex items-center space-x-3'>
                      <div className={`relative transition-all duration-300 ${isActive ? 'text-[#4a90e2]' : 'text-[#9aa5b1] group-hover:text-[#4a90e2]'}`}>
                        {getModuleIcon(item.id, item.icon)}
                        {isActive && (
                          <div className='absolute -top-1 -right-1 w-2 h-2 bg-[#4caf50] rounded-full'></div>
                        )}
                      </div>
                      {!collapsed && (
                        <span className='text-sm font-medium text-left flex-1'>{item.label}</span>
                      )}
                    </div>
                    
                    {!collapsed && item.subItems && (
                      <ChevronDown className={`w-4 h-4 transition-all duration-300 flex-shrink-0 ${
                        isExpanded 
                          ? 'text-[#4a90e2] rotate-180' 
                          : 'text-[#9aa5b1] group-hover:text-[#4a90e2]'
                      }`} />
                    )}
                    
                    {/* Hover effect */}
                    {hoveredItem === item.id && !isActive && (
                      <div className='absolute inset-0 bg-gradient-to-r from-[#4a90e2]/5 to-transparent'></div>
                    )}
                  </button>

                  {!collapsed && item.subItems && isExpanded && (
                    <div className='ml-10 mt-1 space-y-0.5 pl-4 relative'>
                      {/* Vertical line */}
                      <div className='absolute left-0 top-0 bottom-0 w-0.5 bg-gradient-to-b from-[#4a90e2]/20 via-[#4a90e2]/10 to-transparent'></div>
                      
                      {item.subItems.map((subitem) => {
                        const isSubActive = location.pathname === subitem.path
                        const SubIcon = getSubItemIcon(subitem.id)
                        
                        return (
                          <NavLink
                            key={subitem.id}
                            to={subitem.path}
                            className={({ isActive }) => 
                              `block w-full text-sm text-left p-2.5 rounded-xl transition-all duration-200 relative ${
                                isActive
                                  ? 'bg-gradient-to-r from-[#4a90e2]/15 to-transparent text-[#4a90e2] font-semibold shadow-sm'
                                  : 'text-[#64748b] hover:bg-gradient-to-r hover:from-slate-50 hover:to-white hover:text-gray-800'
                              }`
                            }
                          >
                            <div className='flex items-center space-x-3'>
                              <div className={`${isSubActive ? 'text-[#4a90e2]' : 'text-[#9aa5b1]'}`}>
                                {SubIcon}
                              </div>
                              <span>{subitem.label}</span>
                            </div>
                          </NavLink>
                        )
                      })}
                    </div>
                  )}
                </>
              ) : (
                <NavLink
                  to={item.path}
                  className={({ isActive }) => 
                    `w-full flex items-center p-3 rounded-2xl transition-all duration-300 group relative overflow-hidden ${
                      isActive
                        ? 'bg-gradient-to-r from-[#4a90e2]/10 to-[#4a90e2]/5 text-[#4a90e2] font-semibold shadow-sm'
                        : 'text-[#64748b] hover:bg-gradient-to-r hover:from-slate-50 hover:to-white hover:text-gray-800 hover:shadow-sm'
                    }`
                  }
                  onMouseEnter={() => setHoveredItem(item.id)}
                  onMouseLeave={() => setHoveredItem(null)}
                >
                  {/* Active indicator */}
                  {!collapsed && (
                    <>
                      {isActive && (
                        <div className='absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 bg-gradient-to-b from-[#4a90e2] to-[#357ae8] rounded-r-full'></div>
                      )}
                      
                      {/* Hover effect */}
                      {hoveredItem === item.id && !isActive && (
                        <div className='absolute inset-0 bg-gradient-to-r from-[#4a90e2]/5 to-transparent'></div>
                      )}
                    </>
                  )}

                  <div className='flex items-center space-x-3'>
                    <div className={`relative transition-all duration-300 ${isActive ? 'text-[#4a90e2]' : 'text-[#9aa5b1] group-hover:text-[#4a90e2]'}`}>
                      {getModuleIcon(item.id, item.icon)}
                      {isActive && (
                        <div className='absolute -top-1 -right-1 w-2 h-2 bg-[#4caf50] rounded-full'></div>
                      )}
                    </div>
                    {!collapsed && (
                      <span className='text-sm font-medium'>{item.label}</span>
                    )}
                  </div>
                </NavLink>
              )}
            </div>
          )
        })}
      </nav>
      
      {/* Logout Button - Placed above profile card */}
      <div className='px-3 pt-2'>
        <button
          onClick={handleLogout}
          className={`w-full flex items-center p-3 rounded-2xl transition-all duration-300 group relative overflow-hidden text-[#64748b] hover:bg-gradient-to-r hover:from-red-50 hover:to-white hover:text-red-600 hover:shadow-sm ${
            collapsed ? 'justify-center' : ''
          }`}
          onMouseEnter={() => setHoveredItem('logout')}
          onMouseLeave={() => setHoveredItem(null)}
        >
          {/* Hover effect */}
          {hoveredItem === 'logout' && (
            <div className='absolute inset-0 bg-gradient-to-r from-red-500/5 to-transparent'></div>
          )}
          
          <div className='flex items-center space-x-3'>
            <div className='text-[#9aa5b1] group-hover:text-red-500 transition-all duration-300'>
              <LogOut className='w-5 h-5' />
            </div>
            {!collapsed && (
              <span className='text-sm font-medium'>Logout</span>
            )}
          </div>
        </button>
      </div>
      
      {/* Bottom divider */}
      <div className='px-6 pt-2'>
        <div className='h-px bg-gradient-to-r from-transparent via-[#9aa5b1]/20 to-transparent'></div>
      </div>
      
      {/* Profile Card - FIXED: Simple path for public folder image */}
      <div className='p-4 pt-3'>
        <ProfileCard 
          collapsed={collapsed} 
          name="ADMIN" 
          role="Administrator" 
          avatarUrl="/admin.jpg" 
        />
      </div>
    </div>
  )
}

export default Sidebar