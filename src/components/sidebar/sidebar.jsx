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

// Detect the base path dynamically
const getBasePath = () => {
  if (isProduction) {
    const pathParts = window.location.pathname.split('/');
    if (pathParts.length > 1 && pathParts[1] !== '') {
      return `/${pathParts[1]}`;
    }
    return '';
  }
  return '';
}

const BASE_PATH = getBasePath();

// Logout URLs
const LOGOUT_URL = isProduction 
  ? "/index.php"
  : "http://localhost/revenue2/index.php";

// Avatar URL
const AVATAR_URL = isProduction 
  ? `${BASE_PATH}/admin.jpg`
  : "/admin.jpg";

// Color palette
const colors = {
  primary: '#4a90e2',
  primaryLight: 'rgba(74, 144, 226, 0.1)',
  primaryExtraLight: 'rgba(74, 144, 226, 0.05)',
  secondary: '#4caf50',
  secondaryLight: 'rgba(76, 175, 80, 0.1)',
  textPrimary: '#64748b',
  textSecondary: '#9aa5b1',
  background: '#fbfbfb',
  white: '#ffffff',
  border: 'rgba(154, 165, 177, 0.2)',
  hover: 'rgba(74, 144, 226, 0.08)',
  danger: '#ef4444',
  dangerLight: 'rgba(239, 68, 68, 0.1)',
}

// Map module IDs to specific icons
const moduleIcons = {
  dashboard: Home,
  module1: Landmark,
  module2: Briefcase,
  module4: BarChart3,
  module5: CreditCard,
  module6: Store,
  settings: Settings,
}

// Map subitem icons
const subItemIcons = {
  rpt1: LayoutDashboard,
  rpt2: Settings,
  rpt3: CheckCircle,
  rpt4: AlertCircle,
  BusinessTaxDashboard: LayoutDashboard,
  BusinessTaxConfig: Settings,
  BusinessValidation: CheckCircle,
  BusinessStatus: AlertCircle,
  Revenue: BarChart3,
  digidashboard: LayoutDashboard,
  market1: LayoutDashboard,
  market2: MapPin,
  market3: CheckCircle,
  market4: AlertCircle,
  'general-settings': Settings,
  'security-settings': Shield,
}

function Sidebar({ collapsed }) {
  const location = useLocation()
  const navigate = useNavigate()
  const [expandedItem, setExpandedItem] = React.useState(new Set())
  const [hoveredItem, setHoveredItem] = React.useState(null)
  const [imageError, setImageError] = React.useState(false)
  const [showLogout, setShowLogout] = React.useState(false)

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
      if (item.subItems && item.subItems.length > 0 && !item.subItems.some(sub => sub.path === location.pathname)) {
        navigate(item.subItems[0].path)
      }
    }
    setExpandedItem(newExpanded)
  }

  const handleLogout = () => {
    localStorage.removeItem('authToken')
    localStorage.removeItem('userData')
    localStorage.removeItem('userRole')
    sessionStorage.clear()
    window.location.href = LOGOUT_URL
  }

  const handleImageError = () => {
    if (!imageError) {
      console.log('Image failed to load, trying alternative path...');
      setImageError(true);
    }
  }

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

  const getSubItemIcon = (subItemId) => {
    if (subItemIcons[subItemId]) {
      const IconComponent = subItemIcons[subItemId]
      return <IconComponent className="w-4 h-4" />
    }
    return <FileText className="w-4 h-4" />
  }

  const getAvatarUrl = () => {
    if (imageError) {
      return isProduction ? "/admin.jpg" : "/admin.jpg";
    }
    return AVATAR_URL;
  }

  const toggleLogout = () => {
    setShowLogout(!showLogout);
  }

  // Close logout when clicking outside
  React.useEffect(() => {
    const handleClickOutside = (event) => {
      if (!event.target.closest('.profile-container')) {
        setShowLogout(false);
      }
    };

    document.addEventListener('click', handleClickOutside);
    return () => {
      document.removeEventListener('click', handleClickOutside);
    };
  }, []);

  return (
    <div 
      className={`${collapsed ? 'w-20' : 'w-72'} flex flex-col transition-all duration-300 ease-in-out shadow-sm`}
      style={{ 
        backgroundColor: colors.background,
        borderRight: `1px solid ${colors.border}`
      }}
    >
      {/* Logo Section - Simplified */}
      <div className='p-6 pb-4'>
        <NavLink 
          to="/" 
          className='flex items-center space-x-3 group'
          onMouseEnter={() => setHoveredItem('logo')}
          onMouseLeave={() => setHoveredItem(null)}
        >
          <div 
            className={`w-12 h-12 rounded-2xl flex items-center justify-center text-white text-xl font-bold transition-all duration-300 group-hover:scale-105 group-hover:shadow-md`}
            style={{ 
              background: `linear-gradient(135deg, ${colors.primary} 0%, #357ae8 100%)`,
              boxShadow: hoveredItem === 'logo' ? `0 0 0 2px ${colors.primaryLight}` : 'none'
            }}
          >
            <Globe className='w-7 h-7' />
          </div>
          {!collapsed && (
            <div className='transition-all duration-300'>
              <h1 
                className='text-2xl font-bold'
                style={{ 
                  background: `linear-gradient(135deg, #1e293b 0%, ${colors.primary} 100%)`,
                  WebkitBackgroundClip: 'text',
                  WebkitTextFillColor: 'transparent',
                  backgroundClip: 'text'
                }}
              >
                GSM
              </h1>
              <p style={{ color: colors.textSecondary }} className='text-xs mt-0.5 font-medium'>
                Government System Management
              </p>
            </div>
          )}
        </NavLink>
      </div>

      {/* Divider */}
      <div className='px-6 pb-4'>
        <div style={{ backgroundColor: colors.border }} className='h-px'></div>
      </div>

      {/* Navigation Links */}
      <nav className='flex-1 px-3 pb-6 space-y-0.5 overflow-y-auto'>
        {sidebarItems.map((item) => {
          const isActive = item.path === location.pathname || 
            (item.subItems && item.subItems.some(
              subItem => subItem.path === location.pathname
            ))

          const isExpanded = expandedItem.has(item.id)

          return (
            <div key={item.id} className='relative'>
              {item.subItems ? (
                <>
                  <button
                    className={`w-full flex justify-between items-center p-3 rounded-xl transition-all duration-200 group relative overflow-hidden`}
                    style={{
                      backgroundColor: isActive ? colors.primaryLight : 'transparent',
                      color: isActive ? colors.primary : colors.textPrimary,
                      fontWeight: isActive ? 600 : 400,
                    }}
                    onClick={() => toggleExpanded(item)}
                    onMouseEnter={() => setHoveredItem(item.id)}
                    onMouseLeave={() => setHoveredItem(null)}
                  >
                    {/* Active indicator */}
                    {isActive && !collapsed && (
                      <div 
                        className='absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 rounded-r-full'
                        style={{ background: `linear-gradient(to bottom, ${colors.primary}, #357ae8)` }}
                      ></div>
                    )}
                    
                    <div className='flex items-center space-x-3'>
                      <div 
                        className={`relative transition-all duration-200`}
                        style={{ color: isActive ? colors.primary : colors.textSecondary }}
                      >
                        {getModuleIcon(item.id, item.icon)}
                      </div>
                      {!collapsed && (
                        <span className='text-sm text-left flex-1'>{item.label}</span>
                      )}
                    </div>
                    
                    {!collapsed && item.subItems && (
                      <ChevronDown 
                        className={`w-4 h-4 transition-all duration-200 flex-shrink-0`}
                        style={{ 
                          color: isExpanded ? colors.primary : colors.textSecondary,
                          transform: isExpanded ? 'rotate(180deg)' : 'rotate(0deg)'
                        }} 
                      />
                    )}
                    
                    {/* Hover effect */}
                    {hoveredItem === item.id && !isActive && (
                      <div 
                        className='absolute inset-0'
                        style={{ backgroundColor: colors.hover }}
                      ></div>
                    )}
                  </button>

                  {!collapsed && item.subItems && isExpanded && (
                    <div className='ml-10 mt-1 space-y-0.5 pl-4 relative'>
                      {/* Vertical line */}
                      <div 
                        className='absolute left-0 top-0 bottom-0 w-0.5'
                        style={{ 
                          background: `linear-gradient(to bottom, ${colors.primaryLight}, rgba(74, 144, 226, 0.05), transparent)`
                        }}
                      ></div>
                      
                      {item.subItems.map((subitem) => {
                        const isSubActive = location.pathname === subitem.path
                        const SubIcon = getSubItemIcon(subitem.id)
                        
                        return (
                          <NavLink
                            key={subitem.id}
                            to={subitem.path}
                            className={({ isActive }) => `block w-full text-sm text-left p-2.5 rounded-lg transition-all duration-200 relative`}
                            style={({ isActive }) => ({
                              backgroundColor: isActive ? colors.primaryExtraLight : 'transparent',
                              color: isActive ? colors.primary : colors.textPrimary,
                              fontWeight: isActive ? 500 : 400,
                            })}
                          >
                            <div className='flex items-center space-x-3'>
                              <div style={{ color: isSubActive ? colors.primary : colors.textSecondary }}>
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
                  className={({ isActive }) => `w-full flex items-center p-3 rounded-xl transition-all duration-200 group relative overflow-hidden`}
                  style={({ isActive }) => ({
                    backgroundColor: isActive ? colors.primaryLight : 'transparent',
                    color: isActive ? colors.primary : colors.textPrimary,
                    fontWeight: isActive ? 600 : 400,
                  })}
                  onMouseEnter={() => setHoveredItem(item.id)}
                  onMouseLeave={() => setHoveredItem(null)}
                >
                  {/* Active indicator */}
                  {!collapsed && isActive && (
                    <div 
                      className='absolute left-0 top-1/2 -translate-y-1/2 w-1 h-8 rounded-r-full'
                      style={{ background: `linear-gradient(to bottom, ${colors.primary}, #357ae8)` }}
                    ></div>
                  )}
                  
                  {/* Hover effect */}
                  {hoveredItem === item.id && !isActive && (
                    <div 
                      className='absolute inset-0'
                      style={{ backgroundColor: colors.hover }}
                    ></div>
                  )}

                  <div className='flex items-center space-x-3'>
                    <div 
                      className={`relative transition-all duration-200`}
                      style={{ color: isActive ? colors.primary : colors.textSecondary }}
                    >
                      {getModuleIcon(item.id, item.icon)}
                    </div>
                    {!collapsed && (
                      <span className='text-sm'>{item.label}</span>
                    )}
                  </div>
                </NavLink>
              )}
            </div>
          )
        })}
      </nav>
      
      {/* Bottom divider */}
      <div className='px-6 pt-2'>
        <div style={{ backgroundColor: colors.border }} className='h-px'></div>
      </div>
      
      {/* Profile Section with Clickable Admin and Dropdown Logout */}
      <div className='p-4 pt-3 profile-container'>
        {/* Clickable Profile Card */}
        <div 
          onClick={toggleLogout}
          className={`cursor-pointer transition-all duration-200 ${showLogout ? 'rounded-b-none' : ''}`}
          style={{
            borderRadius: showLogout ? '0.75rem 0.75rem 0 0' : '0.75rem',
            border: showLogout ? `1px solid ${colors.border}` : 'none',
            borderBottom: showLogout ? 'none' : 'none',
          }}
        >
          <ProfileCard 
            collapsed={collapsed} 
            name="ADMIN" 
            role="Administrator" 
            avatarUrl={getAvatarUrl()}
            onError={handleImageError}
          />
        </div>
        
        {/* Logout Dropdown - appears when admin is clicked */}
        {showLogout && !collapsed && (
          <div 
            className="overflow-hidden transition-all duration-200 rounded-b-lg"
            style={{
              border: `1px solid ${colors.border}`,
              borderTop: 'none',
              backgroundColor: colors.white,
            }}
          >
            <button
              onClick={handleLogout}
              className='w-full flex items-center justify-center space-x-2 p-3 transition-all duration-200 group relative overflow-hidden'
              style={{
                color: colors.textPrimary,
                backgroundColor: 'transparent'
              }}
              onMouseEnter={() => setHoveredItem('logout')}
              onMouseLeave={() => setHoveredItem(null)}
            >
              {/* Hover effect */}
              {hoveredItem === 'logout' && (
                <div 
                  className='absolute inset-0'
                  style={{ backgroundColor: colors.dangerLight }}
                ></div>
              )}
              
              <div 
                className='transition-all duration-200 group-hover:scale-110'
                style={{ color: hoveredItem === 'logout' ? colors.danger : colors.textSecondary }}
              >
                <LogOut className='w-4 h-4' />
              </div>
              <span 
                className='text-sm font-medium transition-all duration-200'
                style={{ color: hoveredItem === 'logout' ? colors.danger : colors.textPrimary }}
              >
                Sign out
              </span>
            </button>
          </div>
        )}
        
        {/* Collapsed state - show logout popup when admin is clicked */}
        {collapsed && showLogout && (
          <div className='absolute bottom-20 left-16 z-50'>
            <div 
              className="rounded-lg shadow-lg overflow-hidden"
              style={{
                backgroundColor: colors.white,
                border: `1px solid ${colors.border}`,
                minWidth: '140px'
              }}
            >
              <button
                onClick={handleLogout}
                className='w-full flex items-center justify-center space-x-2 p-3 transition-all duration-200 group relative overflow-hidden'
                style={{
                  color: colors.textPrimary,
                  backgroundColor: 'transparent'
                }}
                onMouseEnter={() => setHoveredItem('logout')}
                onMouseLeave={() => setHoveredItem(null)}
              >
                {/* Hover effect */}
                {hoveredItem === 'logout' && (
                  <div 
                    className='absolute inset-0'
                    style={{ backgroundColor: colors.dangerLight }}
                  ></div>
                )}
                
                <div 
                  className='transition-all duration-200 group-hover:scale-110'
                  style={{ color: hoveredItem === 'logout' ? colors.danger : colors.textSecondary }}
                >
                  <LogOut className='w-4 h-4' />
                </div>
                <span 
                  className='text-sm font-medium transition-all duration-200'
                  style={{ color: hoveredItem === 'logout' ? colors.danger : colors.textPrimary }}
                >
                  Sign out
                </span>
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default Sidebar