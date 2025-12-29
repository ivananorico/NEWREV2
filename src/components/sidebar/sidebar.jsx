import React from 'react'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { Globe, ChevronDown } from 'lucide-react'
import sidebarItems from './sidebarItems'
import ProfileCard from './ProfileCard'

function Sidebar({ collapsed }) {
  const location = useLocation()
  const navigate = useNavigate()
  const [expandedItem, setExpandedItem] = React.useState(new Set())

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

  return (
    <div className={`${collapsed ? 'w-16' : 'w-64'} bg-[#fbfbfb] border-r border-slate-200 flex flex-col transition-width duration-200`}>
      {/* Logo */}
      <div className='p-6'>
        <NavLink to="/" className='flex items-center space-x-3'>
          <div className='w-10 h-10 bg-[#4a90e2] rounded-xl flex items-center justify-center text-white text-xl font-bold'>
            <Globe className='w-6 h-6' />
          </div>
          {!collapsed && (
            <div>
              <h1 className='text-xl font-bold text-gray-900'>GSM</h1>
              <p className='text-xs text-[#9aa5b1]'>Admin Dashboard</p>
            </div>
          )}
        </NavLink>
      </div>

      <hr className='border-slate-200 mx-2' />

      {/* Navigation Links */}
      <nav className='flex-1 p-4 space-y-2 overflow-y-auto'>
        {sidebarItems.map((item) => {
          const isActive = item.path === location.pathname || 
            (item.subItems && item.subItems.some(
              subItem => subItem.path === location.pathname
            ))

          return (
            <div key={item.id}>
              {item.subItems ? (
                <>
                  <button
                    className={`w-full flex justify-between items-center p-2 rounded-xl transition-all duration-200 ${
                      isActive
                        ? 'bg-[#4a90e2]/10 text-[#4a90e2] font-semibold border-l-4 border-[#4a90e2]' 
                        : 'text-[#9aa5b1] hover:bg-slate-100 hover:text-gray-700'
                    }`}
                    onClick={() => toggleExpanded(item)}
                  >
                    <div className='flex items-center space-x-3'>
                      <item.icon className='w-5 h-5' />
                      {!collapsed && (
                        <span className='text-sm font-medium'>{item.label}</span>
                      )}
                    </div>
                    {!collapsed && item.subItems && (
                      <ChevronDown className={`w-4 h-4 text-[#9aa5b1] transition-transform duration-200 ${
                        expandedItem.has(item.id) ? 'rotate-180' : ''
                      }`} />
                    )}
                  </button>

                  {!collapsed && item.subItems && expandedItem.has(item.id) && (
                    <div className='ml-8 mt-2 space-y-1 border-l-2 border-slate-200 pl-4'>
                      {item.subItems.map((subitem) => (
                        <NavLink
                          key={subitem.id}
                          to={subitem.path}
                          className={({ isActive }) => 
                            `block w-full text-sm text-left p-2 rounded-lg transition-all duration-150 ${
                              isActive
                                ? 'bg-[#4a90e2]/20 text-[#4a90e2] font-semibold border-l-2 border-[#4a90e2] pl-3 -ml-2'
                                : 'text-[#9aa5b1] hover:bg-slate-100 hover:text-gray-700'
                            }`
                          }
                        >
                          {subitem.label}
                        </NavLink>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <NavLink
                  to={item.path}
                  className={({ isActive }) => 
                    `w-full flex items-center p-2 rounded-xl transition-all duration-200 ${
                      isActive
                        ? 'bg-[#4a90e2]/10 text-[#4a90e2] font-semibold border-l-4 border-[#4a90e2]' 
                        : 'text-[#9aa5b1] hover:bg-slate-100 hover:text-gray-700'
                    }`
                  }
                >
                  <div className='flex items-center space-x-3'>
                    <item.icon className='w-5 h-5' />
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
      
      <hr className='border-slate-200 mx-2' />
      <ProfileCard 
        collapsed={collapsed} 
        name="ADMIN" 
        role="Super Saiyan" 
        avatarUrl="/Bartss.png" 
      />
    </div>
  )
}

export default Sidebar