import { Bell, ChevronDown, Filter, Menu, Search, Settings } from 'lucide-react'
import React from 'react'

function Header({ sidebarCollapsed, onToggleSidebar, breadcrumb = ['Dashboard'] }) {
    const [theme, setTheme] = React.useState('');

    React.useEffect(() => {
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme) {
            setTheme(storedTheme);
            document.documentElement.classList.toggle('dark', storedTheme === 'dark');
        } else {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            setTheme(prefersDark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', prefersDark);
        }
    }, []);

    const toggleTheme = () => {
        const newTheme = theme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
        localStorage.setItem('theme', newTheme);
        document.documentElement.classList.toggle('dark', newTheme === 'dark');
    };

    return (
        <div className='bg-white/-80 backdrop-blur-xl border-b border-slate-200 px-6 py-4 dark:bg-slate-800 dark:border-slate-700/50'>
            <div className='flex items-center justify-between'>
                {/* left */}
                <div className='flex items-center space-x-4'>
                    <button 
                        className='p-2 rounded-lg text-slate-500 hover:bg-slate-200 transition-colors duration-200' 
                        onClick={onToggleSidebar}
                    >
                        <Menu className='w-6 h-6' />
                    </button>
                    <div>
                        <div className='hidden md:flex items-center space-x-1'>
                            <h1 className='text-md font-bold dark:text-white'>PUBLIC ASSETS & FACILITIES MANAGEMENT</h1>
                        </div>
                        <div>
                            <span className='text-xs text-slate-500 font-bold'>
                                {breadcrumb.join(' > ')}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Search Bar */}
                <div className='flex-1 max-w-md mx-8'>
                    <div className='relative'>
                        <Search className='w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-500' />
                        <input 
                            type='text' 
                            placeholder='Search...' 
                            className='w-full pl-10 pr-4 py-2 bg-slate-100 border border-slate-200 rounded-lg placeholder-slate-500 focus:outline-none focus:ring-2 focus:border-transparent focus:ring-orange-300 hover:border-orange-300 transition-all'
                        />
                        <button className='absolute right-2 top-1/2 transform -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600'>
                            <Filter className='w-5 h-5' />
                        </button>
                    </div>
                </div>

                {/* right side*/}
                <div className='flex items-center space-x-1'>
                    {/* notification */}
                    <button className='relative rounded-xl p-2 text-slate-600 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer'>
                        <Bell className='w-6 h-6' />
                        <span className='absolute top-0 w-4 h-4 text-white text-xs bg-red-500 rounded-full flex items-center justify-center'>1</span>
                    </button>

                    {/* dark mode toggle */}
                    <button
                        className='ml-2 rounded-xl p-2 bg-slate-300 text-slate-600 hover:bg-slate-400 dark:bg-slate-700 dark:text-yellow-400 dark:hover:bg-slate-900 transition-colors cursor-pointer'
                        onClick={toggleTheme}
                        aria-label='Toggle dark mode'
                    >
                        {theme === 'dark' ? (
                            // Sun icon for light mode
                            <svg xmlns="http://www.w3.org/2000/svg" className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <circle cx="12" cy="12" r="5" stroke="currentColor" strokeWidth="2" fill="none"/>
                                <path stroke="currentColor" strokeWidth="2" d="M12 1v2m0 18v2m11-11h-2M3 12H1m16.95 7.07l-1.41-1.41M6.46 6.46L5.05 5.05m13.9 0l-1.41 1.41M6.46 17.54l-1.41 1.41"/>
                            </svg>
                        ) : (
                            // Moon icon for dark mode
                            <svg xmlns="http://www.w3.org/2000/svg" className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke="currentColor" strokeWidth="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/>
                            </svg>
                        )}
                    </button>
                </div>
            </div>
        </div>
    )
}

export default Header