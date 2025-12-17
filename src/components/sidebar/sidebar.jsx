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
    <div className={`${collapsed ? 'w-16' : 'w-64'} bg-white border-r border-slate-200/50 flex flex-col transition-width duration-200 dark:bg-slate-900 dark:border-slate-700`}>
      {/* Logo */}
      <div className='p-6'>
        <NavLink to="/" className='flex items-center space-x-3'>
          <div className='w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center text-white text-xl font-bold'>
            <Globe className='w-6 h-6' />
          </div>
          {!collapsed && (
            <div>
              <h1 className='text-xl font-bold dark:text-white'>GSM</h1>
              <p className='text-xs text-slate-500'>Admin Dashboard</p>
            </div>
          )}
        </NavLink>
      </div>

      <hr className='border-slate-200 dark:border-slate-700 mx-2' />

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
                        ? 'bg-orange-200 text-orange-600 font-semibold' 
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:text-slate-600 dark:hover:bg-slate-200'
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
                      <ChevronDown className={`w-4 h-4 text-slate-500 transition-transform duration-200 ${
                        expandedItem.has(item.id) ? 'rotate-180' : ''
                      }`} />
                    )}
                  </button>

                  {!collapsed && item.subItems && expandedItem.has(item.id) && (
                    <div className='ml-8 mt-2 space-y-1 border-l-1 border-slate-300'>
                      {item.subItems.map((subitem) => (
                        <NavLink
                          key={subitem.id}
                          to={subitem.path}
                          className={({ isActive }) => 
                            `block w-full ml-2 text-sm text-left p-2 rounded-lg ${
                              isActive
                                ? 'bg-orange-100 text-orange-700 font-semibold'
                                : 'text-slate-700 dark:text-slate-500 hover:bg-slate-200 dark:hover:text-slate-600 dark:hover:bg-slate-100'
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
                        ? 'bg-orange-200 text-orange-600 font-semibold' 
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:text-slate-600 dark:hover:bg-slate-200'
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
      
      <hr className='border-slate-300 dark:border-slate-700 mx-2' />
      <ProfileCard collapsed={collapsed} name="ADMIN" role="Super Saiyan" avatarUrl="/Bartss.png" />

    </div>
  )
}

export default Sidebar