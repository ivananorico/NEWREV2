import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer
} from 'recharts';
import { 
  CreditCard, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, Download, Filter,
  CheckCircle, Clock, XCircle, FileText, 
  ArrowUpRight, ArrowDownRight, 
  BarChart as BarChartIcon, PieChart as PieChartIcon,
  Database, Filter as FilterIcon,
  ChevronDown, ChevronUp, TrendingUp as TrendingUpIcon,
  Activity, Receipt, Smartphone,
  Globe, Landmark, Building, MapPin, Eye, Banknote, Wallet,
  Search, Percent, Target, Award, 
  AlertTriangle, Info, CalendarDays,
  FileSpreadsheet, History, Zap, Shield,
  Phone, Mail, MessageSquare,
  Cpu, HardDrive, Cloud,
  Navigation, Compass, Target as Target2Icon,
  Layers, Cpu as CpuIcon, Wifi, ShieldCheck,
  BarChart3, Smartphone as SmartphoneIcon,
  CreditCard as CreditCardIcon, Wallet as WalletIcon,
  DollarSign as DollarSignIcon, TrendingUp as TrendingUpIcon2,
  Calendar as CalendarIcon, Users as UsersIcon,
  Package, ShoppingCart, Home, Globe as GlobeIcon,
  Zap as ZapIcon, Cloud as CloudIcon,
  ArrowRight, ChevronRight, ExternalLink,
  CircleDollarSign, Building2, Store, Tag,
  Percent as PercentIcon, Target as TargetIcon,
  TrendingDown as TrendingDownIcon,
  Wallet as Wallet2, Shield as Shield2,
  Smartphone as Smartphone2,
  CreditCard as CreditCard2,
  Cloud as Cloud2,
  ArrowUpRight as ArrowUpRight2,
  Sparkles, AlertOctagon, CheckCircle2,
  ArrowUp, ArrowDown, MoreHorizontal,
  Settings, Copy, Printer,
  EyeOff, Eye as EyeIcon,
  ChevronLeft, ChevronRight as ChevronRightIcon,
  Sliders, Calendar as CalendarIcon2,
  Clock as ClockIcon, Gift, 
  Truck, Car, TreePine, Flame, Scissors, Dog, Trash2, 
  Wrench, Stethoscope, BookOpen, Train, Coffee, Utensils, HardHat, Briefcase,
  Leaf, Wind, Factory, Award as AwardIcon
} from 'lucide-react';
import * as XLSX from 'xlsx';

// ============================================
// 100% DYNAMIC - NO HARDCODED SYSTEMS!
// Only DEFAULT config exists. All systems come from database.
// ============================================

// Custom Icons - Using unique names
const WaterDropIcon = ({ className, strokeWidth }) => (
  <svg 
    xmlns="http://www.w3.org/2000/svg" 
    width="24" 
    height="24" 
    viewBox="0 0 24 24" 
    fill="none" 
    stroke="currentColor" 
    strokeWidth={strokeWidth || 2}
    strokeLinecap="round" 
    strokeLinejoin="round"
    className={className}
  >
    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
  </svg>
);

const CrossIcon = ({ className, strokeWidth }) => (
  <svg 
    xmlns="http://www.w3.org/2000/svg" 
    width="24" 
    height="24" 
    viewBox="0 0 24 24" 
    fill="none" 
    stroke="currentColor" 
    strokeWidth={strokeWidth || 2}
    strokeLinecap="round" 
    strokeLinejoin="round"
    className={className}
  >
    <path d="M12 3v18M3 12h18" />
  </svg>
);

// Enhanced color palette
const COLORS = {
  primary: '#4a90e2',
  primaryLight: '#e8f0fe',
  primaryDark: '#2a5c8a',
  secondary: '#9aa5b1',
  secondaryLight: '#f0f2f4',
  secondaryDark: '#6b7a88',
  success: '#4caf50',
  successLight: '#e8f5e9',
  warning: '#ff9800',
  warningLight: '#fff3e0',
  danger: '#f44336',
  dangerLight: '#ffebee',
  background: '#fbfbfb',
  cardBg: '#ffffff',
  border: '#eaeef2',
  text: {
    primary: '#1a2634',
    secondary: '#6b7a88',
    disabled: '#b8c2cc',
    inverse: '#ffffff'
  },
  chart: {
    gradientStart: '#4a90e2',
    gradientEnd: '#7bb3ff',
    grid: '#eaeef2'
  }
};

// ============================================
// NO HARDCODED SYSTEMS HERE!
// Only DEFAULT config - ALL SYSTEMS COME FROM DATABASE
// ============================================
const SYSTEM_CONFIG = {
  // ONLY DEFAULT - no hardcoded systems!
  'default': { 
    bg: '#9aa5b110', 
    color: '#9aa5b1', 
    border: '#9aa5b120',
    icon: Package, 
    label: 'Other Services',
    category: 'Other'
  }
};

// ============================================
// DYNAMIC SYSTEM CONFIG GENERATOR
// Creates consistent colors/icons for ANY system from database
// ============================================

// Predefined icon pool for variety
const ICON_POOL = [
  Package, ShoppingCart, Home, Globe, Zap, Cloud,
  Building2, Store, MapPin, Car, Award, RefreshCw,
  Calendar, Clock, CreditCard, DollarSign, Users,
  Truck, TreePine, Flame, Scissors, Dog, Trash2,
  Wrench, Stethoscope, BookOpen, Train, Coffee, Utensils, HardHat, Briefcase,
  Leaf, Wind, Factory, CrossIcon, WaterDropIcon
];

// Generate consistent color based on system name
const generateSystemColor = (systemName) => {
  if (!systemName) return SYSTEM_CONFIG.default;
  
  // Simple hash function for consistent colors
  let hash = 0;
  for (let i = 0; i < systemName.length; i++) {
    hash = systemName.charCodeAt(i) + ((hash << 5) - hash);
  }
  
  // Generate HSL color - consistent hue based on hash
  const hue = Math.abs(hash % 360);
  const saturation = 65 + (Math.abs(hash % 20)); // 65-85%
  const lightness = 45 + (Math.abs(hash % 15)); // 45-60%
  
  return {
    bg: `hsla(${hue}, ${saturation}%, 95%, 0.15)`,
    color: `hsl(${hue}, ${saturation}%, ${lightness}%)`,
    border: `hsla(${hue}, ${saturation}%, ${lightness}%, 0.25)`,
    hover: `hsla(${hue}, ${saturation}%, ${lightness}%, 0.1)`
  };
};

// Generate consistent icon based on system name
const generateSystemIcon = (systemName) => {
  if (!systemName) return Package;
  
  // Use hash to pick consistent icon from pool
  let hash = 0;
  for (let i = 0; i < systemName.length; i++) {
    hash = systemName.charCodeAt(i) + ((hash << 5) - hash);
  }
  
  const iconIndex = Math.abs(hash % ICON_POOL.length);
  return ICON_POOL[iconIndex];
};

// Generate friendly label from system name
const generateSystemLabel = (systemName) => {
  if (!systemName) return 'Other Services';
  
  // Convert snake_case or lowercase to Title Case
  return systemName
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ');
};

// Generate category from system name
const generateSystemCategory = (systemName) => {
  if (!systemName) return 'Other';
  
  // Simple categorization based on keywords
  const name = systemName.toLowerCase();
  
  if (name.includes('tax') || name.includes('rpt') || name.includes('property')) 
    return 'Property Tax';
  if (name.includes('business') || name.includes('franchise') || name.includes('permit')) 
    return 'Business Permits';
  if (name.includes('market') || name.includes('stall')) 
    return 'Market';
  if (name.includes('tmm') || name.includes('traffic') || name.includes('car')) 
    return 'Traffic Violations';
  if (name.includes('zoning') || name.includes('building') || name.includes('planning')) 
    return 'Building & Planning';
  if (name.includes('sanitation') || name.includes('health') || name.includes('water')) 
    return 'Health & Sanitation';
  if (name.includes('cemetery') || name.includes('burial') || name.includes('death')) 
    return 'Public Services';
  if (name.includes('wss') || name.includes('utility')) 
    return 'Utilities';
  
  return 'Other Services';
};

// ============================================
// DYNAMIC SYSTEM CONFIG GETTER
// Gets or creates config for ANY system from database
// ============================================
const getSystemConfig = (systemName) => {
  if (!systemName) return SYSTEM_CONFIG.default;
  
  // Return existing config or create dynamic one
  if (!SYSTEM_CONFIG[systemName]) {
    SYSTEM_CONFIG[systemName] = {
      bg: generateSystemColor(systemName).bg,
      color: generateSystemColor(systemName).color,
      border: generateSystemColor(systemName).border,
      icon: generateSystemIcon(systemName),
      label: generateSystemLabel(systemName),
      category: generateSystemCategory(systemName)
    };
  }
  
  return SYSTEM_CONFIG[systemName];
};

// Auto-detect environment
const getApiBase = () => {
  const currentHost = window.location.hostname;
  const currentProtocol = window.location.protocol;
  
  if (currentHost === 'localhost' || currentHost === '127.0.0.1') {
    return 'http://localhost/revenue2/backend/Digital';
  }
  
  if (currentHost.includes('.local') || currentHost.includes('.test')) {
    return `${currentProtocol}//${currentHost}/revenue2/backend/Digital`;
  }
  
  if (currentHost.includes('goserveph.com')) {
    return `${currentProtocol}//revenuetreasury.goserveph.com/backend/Digital`;
  }
  
  const pathParts = window.location.pathname.split('/');
  const revenueIndex = pathParts.indexOf('revenue2');
  
  if (revenueIndex !== -1) {
    const basePath = pathParts.slice(0, revenueIndex + 1).join('/');
    return `${currentProtocol}//${currentHost}${basePath}/backend/Digital`;
  }
  
  return `${currentProtocol}//${currentHost}/backend/Digital`;
};

const API_BASE = getApiBase();

// Format functions
const formatCurrency = (amount) => {
  if (amount === null || amount === undefined || amount === '' || isNaN(amount)) {
    return '₱0';
  }
  
  const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
  
  if (numAmount >= 1000000000) {
    return `₱${(numAmount / 1000000000).toFixed(2)}B`;
  }
  if (numAmount >= 1000000) {
    return `₱${(numAmount / 1000000).toFixed(1)}M`;
  }
  if (numAmount >= 1000) {
    return `₱${(numAmount / 1000).toFixed(1)}K`;
  }
  return `₱${numAmount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const formatNumber = (num) => {
  if (num === null || num === undefined || isNaN(num)) return '0';
  return new Intl.NumberFormat('en-PH').format(num);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const formatShortDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric'
  });
};

// Status Badge Component - Only Paid now
const StatusBadge = ({ status }) => {
  if (status === 'paid') {
    return (
      <span 
        className="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium"
        style={{ 
          backgroundColor: '#e8f5e9',
          color: '#4caf50',
          border: '1px solid #4caf5020'
        }}
      >
        <CheckCircle2 className="w-3 h-3 mr-1.5" strokeWidth={2.5} />
        Paid
      </span>
    );
  }
  return null;
};

// Method Badge Component
const MethodBadge = ({ method }) => {
  const config = {
    gcash: {
      bg: '#4a90e210',
      color: '#4a90e2',
      border: '#4a90e220',
      icon: Smartphone2,
      text: 'GCash'
    },
    maya: {
      bg: '#5a2e8c10',
      color: '#5a2e8c',
      border: '#5a2e8c20',
      icon: Wallet2,
      text: 'Maya'
    },
    card: {
      bg: '#2196f310',
      color: '#2196f3',
      border: '#2196f320',
      icon: CreditCard2,
      text: 'Card'
    }
  };

  const { bg, color, border, icon: Icon, text } = config[method] || {
    bg: '#f0f2f4',
    color: '#6b7a88',
    border: '#9aa5b120',
    icon: CreditCard2,
    text: method || 'Unknown'
  };

  return (
    <span 
      className="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium"
      style={{ 
        backgroundColor: bg,
        color: color,
        border: `1px solid ${border}`
      }}
    >
      <Icon className="w-3 h-3 mr-1.5" strokeWidth={2.5} />
      {text}
    </span>
  );
};

// ============================================
// DYNAMIC System Badge Component
// Works with ANY system from database - NO HARDCODING!
// ============================================
const SystemBadge = ({ system }) => {
  const config = getSystemConfig(system);
  const Icon = config.icon;

  return (
    <span 
      className="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium"
      style={{ 
        backgroundColor: config.bg,
        color: config.color,
        border: `1px solid ${config.border}`
      }}
    >
      <Icon className="w-3 h-3 mr-1.5" strokeWidth={2.5} />
      <span className="font-medium">{config.label}</span>
    </span>
  );
};

// Stat Card Component
const StatCard = ({ title, value, icon: Icon, color, subtitle, loading }) => {
  if (loading) {
    return (
      <div className="bg-white rounded-2xl p-6 border animate-pulse" style={{ borderColor: '#eaeef2' }}>
        <div className="h-20 bg-gray-100 rounded"></div>
      </div>
    );
  }

  return (
    <div 
      className="bg-white rounded-2xl p-6 border hover:shadow-lg transition-all duration-300"
      style={{ borderColor: '#eaeef2' }}
    >
      <div className="flex items-start justify-between">
        <div>
          <p className="text-sm font-medium mb-1" style={{ color: '#6b7a88' }}>
            {title}
          </p>
          <p className="text-3xl font-bold" style={{ color: '#1a2634' }}>
            {value}
          </p>
          {subtitle && (
            <p className="text-xs mt-1" style={{ color: '#6b7a88' }}>
              {subtitle}
            </p>
          )}
        </div>
        <div 
          className="p-3 rounded-2xl"
          style={{ 
            backgroundColor: `${color}10`,
            color: color
          }}
        >
          <Icon className="w-6 h-6" strokeWidth={1.5} />
        </div>
      </div>
    </div>
  );
};

// Progress Bar Component
const ProgressBar = ({ value, max = 100, color, label, showLabel = true }) => {
  const percentage = Math.min((value / max) * 100, 100);
  
  return (
    <div className="space-y-1.5">
      {showLabel && (
        <div className="flex justify-between text-xs">
          <span style={{ color: '#6b7a88' }}>{label}</span>
          <span className="font-medium" style={{ color: '#1a2634' }}>{percentage.toFixed(1)}%</span>
        </div>
      )}
      <div className="h-2 rounded-full" style={{ backgroundColor: `${color}15` }}>
        <div 
          className="h-full rounded-full transition-all duration-500"
          style={{ 
            width: `${percentage}%`,
            backgroundColor: color
          }}
        />
      </div>
    </div>
  );
};

// Custom Tooltip Component
const CustomTooltip = ({ active, payload, label, type = 'currency' }) => {
  if (active && payload && payload.length) {
    return (
      <div className="bg-white p-4 rounded-xl shadow-lg border" style={{ borderColor: '#eaeef2' }}>
        <p className="text-sm font-medium mb-2" style={{ color: '#1a2634' }}>{label}</p>
        {payload.map((entry, index) => (
          <div key={index} className="flex items-center gap-2 text-sm">
            <div className="w-2 h-2 rounded-full" style={{ backgroundColor: entry.color }} />
            <span style={{ color: '#6b7a88' }}>{entry.name}:</span>
            <span className="font-bold" style={{ color: entry.color }}>
              {type === 'currency' ? formatCurrency(entry.value) : entry.value}
            </span>
          </div>
        ))}
      </div>
    );
  }
  return null;
};

// ============================================
// SIMPLIFIED ALL SYSTEMS VIEW
// Shows ONLY: Total Revenue, Transactions, Last Activity, Share of Total Revenue
// 100% dynamic - only systems from your database
// ============================================
const AllSystemsView = ({ systems, loading }) => {
  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="bg-white rounded-2xl border p-6 animate-pulse" style={{ borderColor: '#eaeef2' }}>
            <div className="h-32 bg-gray-100 rounded"></div>
          </div>
        ))}
      </div>
    );
  }

  if (systems.length === 0) {
    return (
      <div className="bg-white rounded-2xl border p-12 text-center" style={{ borderColor: '#eaeef2' }}>
        <div className="flex flex-col items-center">
          <div className="p-4 rounded-full" style={{ backgroundColor: '#f0f2f4' }}>
            <Database className="w-12 h-12" style={{ color: '#6b7a88' }} strokeWidth={1.5} />
          </div>
          <h3 className="text-lg font-semibold mt-4" style={{ color: '#1a2634' }}>
            No Systems Found
          </h3>
          <p className="text-sm mt-2 max-w-md" style={{ color: '#6b7a88' }}>
            There are no payment transactions in the database yet. 
            Once payments are made, systems will appear here automatically.
          </p>
        </div>
      </div>
    );
  }

  // Calculate total revenue across all systems
  const totalRevenue = systems.reduce((sum, s) => sum + s.amount, 0);
  const totalTransactions = systems.reduce((sum, s) => sum + s.count, 0);

  return (
    <div className="space-y-6">
      {/* Quick Stats Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
          <div className="flex items-center gap-3">
            <div className="p-3 rounded-xl" style={{ backgroundColor: '#4a90e210' }}>
              <Layers className="w-6 h-6" style={{ color: '#4a90e2' }} />
            </div>
            <div>
              <p className="text-sm font-medium" style={{ color: '#6b7a88' }}>Total Systems</p>
              <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>{systems.length}</p>
            </div>
          </div>
        </div>
        
        <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
          <div className="flex items-center gap-3">
            <div className="p-3 rounded-xl" style={{ backgroundColor: '#4caf5010' }}>
              <DollarSign className="w-6 h-6" style={{ color: '#4caf50' }} />
            </div>
            <div>
              <p className="text-sm font-medium" style={{ color: '#6b7a88' }}>Total Revenue (All Time)</p>
              <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>{formatCurrency(totalRevenue)}</p>
            </div>
          </div>
        </div>
        
        <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
          <div className="flex items-center gap-3">
            <div className="p-3 rounded-xl" style={{ backgroundColor: '#ff980010' }}>
              <FileText className="w-6 h-6" style={{ color: '#ff9800' }} />
            </div>
            <div>
              <p className="text-sm font-medium" style={{ color: '#6b7a88' }}>Total Transactions</p>
              <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>{formatNumber(totalTransactions)}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Systems Grid - ONLY systems from your database */}
      <div className="bg-white rounded-2xl border overflow-hidden" style={{ borderColor: '#eaeef2' }}>
        <div className="p-6 border-b" style={{ borderColor: '#eaeef2' }}>
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-lg font-semibold" style={{ color: '#1a2634' }}>
                Connected Systems
              </h3>
              <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                These {systems.length} system{systems.length !== 1 ? 's' : ''} have made payments through the gateway
              </p>
            </div>
            <div className="px-3 py-1.5 rounded-full text-xs font-medium" style={{ backgroundColor: '#4a90e210', color: '#4a90e2' }}>
              <Database className="w-3 h-3 inline mr-1" />
              From Your Database
            </div>
          </div>
        </div>
        
        <div className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {systems.map((system) => {
              const Icon = system.icon;
              const revenuePercentage = totalRevenue > 0 ? (system.amount / totalRevenue * 100).toFixed(1) : 0;
              const isActive = system.last_active && 
                (new Date() - new Date(system.last_active)) < 30 * 24 * 60 * 60 * 1000; // Active in last 30 days
              
              return (
                <div 
                  key={system.system} 
                  className="rounded-xl border p-5 hover:shadow-lg transition-all"
                  style={{ 
                    borderColor: `${system.color}30`,
                    backgroundColor: 'white'
                  }}
                >
                  {/* System Header - Only system name and icon */}
                  <div className="flex items-center gap-3 mb-4">
                    <div 
                      className="p-3 rounded-xl"
                      style={{ backgroundColor: `${system.color}10` }}
                    >
                      <Icon className="w-5 h-5" style={{ color: system.color }} />
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center justify-between">
                        <h4 className="font-semibold" style={{ color: '#1a2634' }}>
                          {system.name}
                        </h4>
                        <span 
                          className="text-xs px-2 py-1 rounded-full"
                          style={{ 
                            backgroundColor: isActive ? '#e8f5e9' : '#f0f2f4',
                            color: isActive ? '#4caf50' : '#6b7a88' 
                          }}
                        >
                          {isActive ? 'Active' : 'Inactive'}
                        </span>
                      </div>
                      <p className="text-xs mt-0.5" style={{ color: '#6b7a88' }}>
                        {system.category}
                      </p>
                    </div>
                  </div>
                  
                  {/* ONLY the 4 requested metrics */}
                  <div className="space-y-4">
                    {/* 1. Total Revenue */}
                    <div className="flex justify-between items-center">
                      <span className="text-sm" style={{ color: '#6b7a88' }}>
                        <DollarSign className="w-3.5 h-3.5 inline mr-1" />
                        Total Revenue
                      </span>
                      <span className="text-lg font-bold" style={{ color: system.color }}>
                        {formatCurrency(system.amount)}
                      </span>
                    </div>
                    
                    {/* 2. Transactions */}
                    <div className="flex justify-between items-center">
                      <span className="text-sm" style={{ color: '#6b7a88' }}>
                        <FileText className="w-3.5 h-3.5 inline mr-1" />
                        Transactions
                      </span>
                      <span className="font-medium" style={{ color: '#1a2634' }}>
                        {formatNumber(system.count)}
                      </span>
                    </div>
                    
                    {/* 3. Last Activity */}
                    <div className="flex justify-between items-center">
                      <span className="text-sm" style={{ color: '#6b7a88' }}>
                        <Clock className="w-3.5 h-3.5 inline mr-1" />
                        Last Activity
                      </span>
                      <span className="text-sm" style={{ color: '#6b7a88' }}>
                        {system.last_active ? formatShortDate(system.last_active) : 'Never'}
                      </span>
                    </div>
                    
                    {/* 4. Share of Total Revenue - with progress bar */}
                    <div className="pt-2">
                      <div className="flex justify-between text-xs mb-1.5">
                        <span style={{ color: '#6b7a88' }}>
                          <Percent className="w-3 h-3 inline mr-1" />
                          Share of Total Revenue
                        </span>
                        <span className="font-medium" style={{ color: system.color }}>
                          {revenuePercentage}%
                        </span>
                      </div>
                      <div className="h-2 rounded-full" style={{ backgroundColor: `${system.color}15` }}>
                        <div 
                          className="h-full rounded-full"
                          style={{ 
                            width: `${revenuePercentage}%`,
                            backgroundColor: system.color
                          }}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
};

// ============================================
// MAIN DASHBOARD COMPONENT
// ============================================
export default function DigiDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [payments, setPayments] = useState([]);
  const [stats, setStats] = useState(null);
  const [allSystems, setAllSystems] = useState([]);
  const [exportLoading, setExportLoading] = useState(false);
  const [dateRange, setDateRange] = useState({
    startDate: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
  });
  const [filters, setFilters] = useState({
    payment_method: 'all',
    client_system: 'all',
    search: ''
  });
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(15);
  const [activeTab, setActiveTab] = useState('overview');
  const [timeRange, setTimeRange] = useState('month');

  useEffect(() => {
    fetchAllData();
  }, [dateRange, currentPage, timeRange]);

  const fetchAllData = async () => {
    setLoading(true);
    try {
      await Promise.all([
        fetchPayments(),
        fetchStats(),
        fetchAllSystems()
      ]);
    } catch (err) {
      console.error('Error fetching data:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchPayments = async () => {
    try {
      let url = `${API_BASE}/payments.php?action=get_payments&start_date=${dateRange.startDate}&end_date=${dateRange.endDate}&page=${currentPage}&limit=${itemsPerPage}`;
      
      if (filters.payment_method !== 'all') {
        url += `&payment_method=${filters.payment_method}`;
      }
      if (filters.client_system !== 'all') {
        url += `&client_system=${filters.client_system}`;
      }
      if (filters.search) {
        url += `&search=${encodeURIComponent(filters.search)}`;
      }
      
      const response = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.error || 'Failed to load payments');
      }
      
      setPayments(data.data || []);
      
    } catch (err) {
      console.error('Error fetching payments:', err);
      throw err;
    }
  };

  const fetchStats = async () => {
    try {
      const response = await fetch(`${API_BASE}/payments.php?action=get_stats&start_date=${dateRange.startDate}&end_date=${dateRange.endDate}`);
      const data = await response.json();
      
      if (data.success) {
        setStats(data.data);
      } else {
        throw new Error(data.error || 'Failed to load statistics');
      }
    } catch (err) {
      console.error('Error fetching stats:', err);
      throw err;
    }
  };

  const fetchAllSystems = async () => {
    try {
      const response = await fetch(`${API_BASE}/payments.php?action=get_all_systems`);
      const data = await response.json();
      
      if (data.success) {
        setAllSystems(data.data || []);
      } else {
        console.error('Failed to load all systems:', data.error);
      }
    } catch (err) {
      console.error('Error fetching all systems:', err);
    }
  };

  const exportToExcel = () => {
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      
      const wsPayments = XLSX.utils.json_to_sheet(payments.map(p => ({
        'Payment ID': p.payment_id,
        'System': getSystemConfig(p.client_system).label,
        'Reference': p.client_reference,
        'Purpose': p.purpose,
        'Amount': parseFloat(p.amount),
        'Phone': p.phone,
        'Payment Method': p.payment_method,
        'Status': p.payment_status,
        'OTP Verified': p.otp_verified ? 'Yes' : 'No',
        'Receipt No.': p.receipt_number,
        'Created At': p.created_at,
        'Paid At': p.paid_at,
        'Callback Sent': p.callback_sent ? 'Yes' : 'No'
      })));
      XLSX.utils.book_append_sheet(wb, wsPayments, 'Payments');
      
      const wsSystems = XLSX.utils.json_to_sheet(allSystems.map(s => ({
        'System': s.system,
        'Label': getSystemConfig(s.system).label,
        'Category': getSystemConfig(s.system).category,
        'Total Transactions': s.total_transactions,
        'Total Amount': s.total_amount,
        'Paid Amount': s.paid_amount,
        'Success Rate': `${s.success_rate}%`,
        'Last Active': formatDate(s.last_transaction)
      })));
      XLSX.utils.book_append_sheet(wb, wsSystems, 'All Systems');
      
      if (stats) {
        const wsStats = XLSX.utils.json_to_sheet([
          {
            'Total Transactions': stats.total_transactions,
            'Total Amount': stats.total_amount,
            'Paid Count': stats.paid_count,
            'Success Rate': `${stats.success_rate || 0}%`,
            'Average Amount': stats.average_amount
          }
        ]);
        XLSX.utils.book_append_sheet(wb, wsStats, 'Summary');
      }
      
      const filename = `Digital_Payments_${dateRange.startDate}_to_${dateRange.endDate}.xlsx`;
      XLSX.writeFile(wb, filename);
      
    } catch (error) {
      console.error('Export error:', error);
      alert('Error exporting to Excel');
    } finally {
      setExportLoading(false);
    }
  };

  const handleFilterChange = (filterType, value) => {
    setFilters(prev => ({
      ...prev,
      [filterType]: value
    }));
    setCurrentPage(1);
  };

  const handleDateChange = (type, value) => {
    setDateRange(prev => ({
      ...prev,
      [type]: value
    }));
    setCurrentPage(1);
  };

  const getUniqueSystems = () => {
    return allSystems.map(s => s.system).sort();
  };

  const getAllSystemsChartData = () => {
    return allSystems.map(system => {
      const config = getSystemConfig(system.system);
      return {
        system: system.system,
        name: config.label,
        count: system.total_transactions,
        amount: system.total_amount,
        paidAmount: system.paid_amount,
        color: config.color,
        bg: config.bg,
        border: config.border,
        icon: config.icon,
        category: config.category,
        success_rate: system.success_rate,
        last_active: system.last_transaction,
        current_month_amount: system.current_month_amount
      };
    }).sort((a, b) => b.amount - a.amount);
  };

  const getSystemChartData = () => {
    if (!stats || !stats.by_system) return [];
    
    return Object.keys(stats.by_system).map(system => {
      const config = getSystemConfig(system);
      return {
        system: system,
        name: config.label,
        count: stats.by_system[system].count,
        amount: stats.by_system[system].amount,
        color: config.color
      };
    }).sort((a, b) => b.amount - a.amount);
  };

  // Get daily trend data - ONLY PAID transactions
  const getDailyTrendData = () => {
    if (!payments.length) return [];
    
    const dailyMap = new Map();
    
    payments.forEach(payment => {
      const date = payment.created_at?.split(' ')[0];
      if (!date) return;
      
      if (!dailyMap.has(date)) {
        dailyMap.set(date, {
          date: formatShortDate(date),
          fullDate: date,
          amount: 0,
          count: 0
        });
      }
      
      const data = dailyMap.get(date);
      data.amount += parseFloat(payment.amount) || 0;
      data.count += 1;
    });
    
    return Array.from(dailyMap.values()).sort((a, b) => 
      new Date(a.fullDate) - new Date(b.fullDate)
    );
  };

  // Get method chart data - ONLY PAID transactions
  const getMethodChartData = () => {
    const methodMap = new Map();
    
    payments.forEach(payment => {
      const method = payment.payment_method;
      if (!method) return;
      
      if (!methodMap.has(method)) {
        methodMap.set(method, {
          name: method === 'gcash' ? 'GCash' : 
                method === 'maya' ? 'Maya' : 
                method === 'card' ? 'Card' : method,
          value: 0,
          amount: 0,
          color: method === 'gcash' ? '#4a90e2' : 
                 method === 'maya' ? '#5a2e8c' : 
                 method === 'card' ? '#2196f3' : '#9aa5b1'
        });
      }
      
      const data = methodMap.get(method);
      data.value += 1;
      data.amount += parseFloat(payment.amount) || 0;
    });
    
    return Array.from(methodMap.values());
  };

  // Calculate summary stats from payments - ONLY PAID
  const calculateStats = () => {
    const paid = payments.length;
    const totalAmount = payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
    
    return {
      total_transactions: paid,
      paid_count: paid,
      total_amount: totalAmount,
      success_rate: 100,
      average_amount: paid > 0 ? totalAmount / paid : 0
    };
  };

  const displayStats = stats || calculateStats();
  const systemChartData = getSystemChartData();
  const allSystemsChartData = getAllSystemsChartData();
  const dailyTrendData = getDailyTrendData();
  const methodChartData = getMethodChartData();
  const uniqueSystems = getUniqueSystems();

  if (loading && payments.length === 0 && allSystems.length === 0) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: '#fbfbfb' }}>
        <div className="text-center">
          <div className="relative">
            <div className="animate-spin rounded-full h-16 w-16 border-4 mx-auto mb-4" 
                 style={{ borderColor: '#4a90e220', borderTopColor: '#4a90e2' }} />
            <Sparkles className="w-6 h-6 absolute top-5 left-5 animate-pulse" style={{ color: '#4a90e2' }} />
          </div>
          <p className="text-sm font-medium" style={{ color: '#6b7a88' }}>Loading Digital Payment Dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: '#fbfbfb' }}>
      {/* Header Section - REMOVED STICKY */}
      <div className="border-b bg-white" style={{ borderColor: '#eaeef2' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          {/* Header Top Row - Fixed alignment */}
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-4">
            <div className="flex items-center gap-3">
              <div 
                className="p-2 rounded-xl"
                style={{ backgroundColor: '#4a90e210' }}
              >
                <Cloud2 className="w-6 h-6" style={{ color: '#4a90e2' }} strokeWidth={1.5} />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-xl font-bold" style={{ color: '#1a2634' }}>
                    Digital Payment Gateway
                  </h1>
                  <span className="px-2 py-0.5 text-xs font-medium rounded-full" 
                        style={{ backgroundColor: '#e8f5e9', color: '#4caf50' }}>
                    Live
                  </span>
                </div>
                <div className="flex items-center gap-2 mt-0.5">
                  <div className="flex items-center gap-1 text-xs" style={{ color: '#6b7a88' }}>
                    <CalendarIcon className="w-3 h-3" />
                    <span>{dateRange.startDate} - {dateRange.endDate}</span>
                  </div>
                  <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
                  <div className="flex items-center gap-1 text-xs" style={{ color: '#6b7a88' }}>
                    <Database className="w-3 h-3" />
                    <span>{formatNumber(payments.length)} txns</span>
                  </div>
                  <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
                  <div className="flex items-center gap-1 text-xs" style={{ color: '#6b7a88' }}>
                    <Layers className="w-3 h-3" />
                    <span>{allSystems.length} systems</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div className="flex items-center gap-2">
              {/* Date Range Picker - Compact */}
              <div className="flex items-center border rounded-lg bg-white text-xs" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-center divide-x" style={{ borderColor: '#eaeef2' }}>
                  <input
                    type="date"
                    value={dateRange.startDate}
                    onChange={(e) => handleDateChange('startDate', e.target.value)}
                    className="px-2 py-1.5 text-xs border-0 focus:ring-0 focus:outline-none rounded-l-lg w-28"
                    style={{ color: '#1a2634' }}
                  />
                  <input
                    type="date"
                    value={dateRange.endDate}
                    onChange={(e) => handleDateChange('endDate', e.target.value)}
                    className="px-2 py-1.5 text-xs border-0 focus:ring-0 focus:outline-none rounded-r-lg w-28"
                    style={{ color: '#1a2634' }}
                  />
                </div>
              </div>
              
              {/* Export Button - Small */}
              <button
                onClick={exportToExcel}
                disabled={exportLoading}
                className="px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-all hover:opacity-90 text-xs"
                style={{ backgroundColor: '#4a90e2', color: 'white' }}
              >
                {exportLoading ? (
                  <>
                    <div className="animate-spin rounded-full h-3 w-3 border-2 border-white border-t-transparent" />
                    <span>...</span>
                  </>
                ) : (
                  <>
                    <Download className="w-3.5 h-3.5" />
                    <span>Export</span>
                  </>
                )}
              </button>
              
              {/* Refresh Button - Small */}
              <button
                onClick={fetchAllData}
                className="p-1.5 rounded-lg border hover:bg-gray-50 transition-all"
                style={{ borderColor: '#eaeef2', color: '#6b7a88' }}
              >
                <RefreshCw className="w-4 h-4" />
              </button>
            </div>
          </div>
          
          {/* Navigation Tabs - Without sticky */}
          <div className="flex items-center justify-between">
            <nav className="flex space-x-1">
              {[
                { id: 'overview', label: 'Overview', icon: BarChart3 },
                { id: 'transactions', label: 'Transactions', icon: FileText },
                { id: 'analytics', label: 'Analytics', icon: TrendingUpIcon2 },
                { id: 'systems', label: 'Connected Systems', icon: Layers }
              ].map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.id;
                
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`px-3 py-1.5 rounded-lg flex items-center gap-1.5 text-xs font-medium transition-all`}
                    style={{ 
                      backgroundColor: isActive ? '#4a90e210' : 'transparent',
                      color: isActive ? '#4a90e2' : '#6b7a88'
                    }}
                  >
                    <Icon className="w-3.5 h-3.5" strokeWidth={2} />
                    {tab.label}
                  </button>
                );
              })}
            </nav>
            
            {/* Time Range Quick Select - Compact */}
            <div className="flex items-center gap-1 p-0.5 rounded-lg" style={{ backgroundColor: '#f0f2f4' }}>
              {['day', 'week', 'month', 'year'].map((range) => (
                <button
                  key={range}
                  onClick={() => setTimeRange(range)}
                  className={`px-2 py-1 rounded-lg text-xs font-medium capitalize transition-all`}
                  style={{ 
                    backgroundColor: timeRange === range ? 'white' : 'transparent',
                    color: timeRange === range ? '#4a90e2' : '#6b7a88',
                    boxShadow: timeRange === range ? '0 1px 4px rgba(0,0,0,0.02)' : 'none'
                  }}
                >
                  {range}
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        {/* OVERVIEW TAB */}
        {activeTab === 'overview' && (
          <>
            {/* Key Metrics Grid - Removed Pending */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
              <StatCard
                title="Total Collection"
                value={formatCurrency(displayStats.total_amount)}
                icon={Wallet2}
                color="#4a90e2"
                subtitle={`${formatNumber(displayStats.total_transactions)} transactions`}
              />
              
              <StatCard
                title="Successful Payments"
                value={formatNumber(displayStats.paid_count)}
                icon={CheckCircle2}
                color="#4caf50"
                subtitle="100% success rate"
              />
              
              <StatCard
                title="Average Transaction"
                value={formatCurrency(displayStats.average_amount)}
                icon={TrendingUpIcon}
                color="#2196f3"
              />
              
              <StatCard
                title="Active Systems"
                value={formatNumber(systemChartData.length)}
                icon={Layers}
                color="#9c27b0"
                subtitle="Systems with transactions"
              />
            </div>

            {/* Revenue Overview & System Distribution */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
              {/* Daily Revenue Chart - BAR CHART ONLY */}
              <div className="lg:col-span-2 bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex justify-between items-center mb-4">
                  <div>
                    <h3 className="text-base font-semibold flex items-center gap-2" style={{ color: '#1a2634' }}>
                      <Activity className="w-4 h-4" style={{ color: '#4a90e2' }} />
                      Revenue Trend
                    </h3>
                    <p className="text-xs mt-1" style={{ color: '#6b7a88' }}>
                      Daily collection amount
                    </p>
                  </div>
                </div>
                
                <div className="h-72">
                  {dailyTrendData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={dailyTrendData}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#eaeef2" vertical={false} />
                        <XAxis 
                          dataKey="date" 
                          axisLine={false}
                          tickLine={false}
                          tick={{ fill: '#6b7a88', fontSize: 11 }}
                        />
                        <YAxis 
                          axisLine={false}
                          tickLine={false}
                          tick={{ fill: '#6b7a88', fontSize: 11 }}
                          tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                        />
                        <Tooltip content={<CustomTooltip />} />
                        <Bar 
                          dataKey="amount" 
                          name="Amount" 
                          fill="#4a90e2"
                          radius={[4, 4, 0, 0]}
                          barSize={24}
                        />
                      </BarChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full" style={{ color: '#6b7a88' }}>
                      <BarChartIcon className="w-10 h-10 mb-2" strokeWidth={1.5} />
                      <p className="text-sm font-medium">No transaction data available</p>
                    </div>
                  )}
                </div>
              </div>

              {/* System Distribution */}
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <h3 className="text-base font-semibold flex items-center gap-2 mb-4" style={{ color: '#1a2634' }}>
                  <PieChartIcon className="w-4 h-4" style={{ color: '#9c27b0' }} />
                  Revenue by System
                </h3>
                
                <div className="h-40">
                  {systemChartData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={systemChartData}
                          cx="50%"
                          cy="50%"
                          innerRadius={45}
                          outerRadius={60}
                          paddingAngle={4}
                          dataKey="amount"
                        >
                          {systemChartData.map((entry, index) => (
                            <Cell 
                              key={`cell-${index}`} 
                              fill={entry.color}
                              stroke="white"
                              strokeWidth={1.5}
                            />
                          ))}
                        </Pie>
                        <Tooltip 
                          formatter={(value, name, props) => [
                            formatCurrency(value),
                            props.payload.name
                          ]}
                          contentStyle={{
                            backgroundColor: 'white',
                            border: `1px solid #eaeef2`,
                            borderRadius: '8px',
                            padding: '6px 10px',
                            fontSize: '11px'
                          }}
                        />
                      </PieChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full" style={{ color: '#6b7a88' }}>
                      <PieChartIcon className="w-10 h-10 mb-2" strokeWidth={1.5} />
                      <p className="text-xs">No system data</p>
                    </div>
                  )}
                </div>

                <div className="mt-4 space-y-2 max-h-36 overflow-y-auto">
                  {systemChartData.slice(0, 5).map((system) => (
                    <div key={system.system} className="flex items-center justify-between text-xs">
                      <div className="flex items-center gap-1.5">
                        <div className="w-2 h-2 rounded-full" style={{ backgroundColor: system.color }} />
                        <span style={{ color: '#6b7a88' }}>
                          {system.name}
                        </span>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="font-medium" style={{ color: '#1a2634' }}>
                          {formatCurrency(system.amount)}
                        </span>
                        <span style={{ color: '#6b7a88' }}>
                          ({displayStats.total_amount ? ((system.amount / displayStats.total_amount) * 100).toFixed(1) : 0}%)
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Payment Methods & Recent Transactions */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
              {/* Payment Methods */}
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex justify-between items-center mb-4">
                  <div>
                    <h3 className="text-base font-semibold" style={{ color: '#1a2634' }}>
                      Payment Methods
                    </h3>
                  </div>
                  <Smartphone2 className="w-4 h-4" style={{ color: '#4a90e2' }} strokeWidth={1.5} />
                </div>

                <div className="space-y-4">
                  {methodChartData.length > 0 ? (
                    methodChartData.map((method) => (
                      <div key={method.name} className="p-3 rounded-lg" style={{ backgroundColor: `${method.color}10` }}>
                        <div className="flex items-center justify-between mb-2">
                          <div className="flex items-center gap-2">
                            <div className="p-1.5 rounded-lg bg-white">
                              {method.name === 'GCash' && <Smartphone2 className="w-4 h-4" style={{ color: method.color }} />}
                              {method.name === 'Maya' && <Wallet2 className="w-4 h-4" style={{ color: method.color }} />}
                              {method.name === 'Card' && <CreditCard2 className="w-4 h-4" style={{ color: method.color }} />}
                            </div>
                            <div>
                              <span className="text-xs font-medium" style={{ color: '#1a2634' }}>{method.name}</span>
                              <p className="text-xs" style={{ color: '#6b7a88' }}>
                                {formatNumber(method.value)} txns
                              </p>
                            </div>
                          </div>
                          <span className="text-sm font-bold" style={{ color: method.color }}>
                            {displayStats.total_transactions ? ((method.value / displayStats.total_transactions) * 100).toFixed(0) : 0}%
                          </span>
                        </div>
                        <ProgressBar 
                          value={method.value} 
                          max={displayStats.total_transactions} 
                          color={method.color} 
                          showLabel={false}
                        />
                      </div>
                    ))
                  ) : (
                    <div className="text-center py-6" style={{ color: '#6b7a88' }}>
                      <CreditCard2 className="w-10 h-10 mx-auto mb-2" strokeWidth={1.5} />
                      <p className="text-xs">No payment method data</p>
                    </div>
                  )}
                </div>
              </div>

              {/* Recent Transactions - Only Paid */}
              <div className="lg:col-span-2 bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex justify-between items-center mb-4">
                  <div>
                    <h3 className="text-base font-semibold" style={{ color: '#1a2634' }}>
                      Recent Transactions
                    </h3>
                    <p className="text-xs mt-1" style={{ color: '#6b7a88' }}>
                      Successful payments only
                    </p>
                  </div>
                  <button 
                    onClick={() => setActiveTab('transactions')}
                    className="text-xs font-medium flex items-center gap-1 hover:gap-2 transition-all"
                    style={{ color: '#4a90e2' }}
                  >
                    View All
                    <ChevronRight className="w-3 h-3" />
                  </button>
                </div>

                <div className="space-y-2 max-h-80 overflow-y-auto">
                  {payments.slice(0, 6).map((payment) => {
                    const config = getSystemConfig(payment.client_system);
                    const Icon = config.icon;
                    
                    return (
                      <div 
                        key={payment.id} 
                        className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 transition-colors"
                      >
                        <div className="flex items-start gap-2">
                          <div 
                            className="p-1.5 rounded-lg"
                            style={{ backgroundColor: config.bg }}
                          >
                            <Icon className="w-3.5 h-3.5" style={{ color: config.color }} />
                          </div>
                          <div>
                            <div className="flex items-center gap-1.5">
                              <span className="text-xs font-medium" style={{ color: '#1a2634' }}>
                                {config.label}
                              </span>
                              <StatusBadge status={payment.payment_status} />
                            </div>
                            <p className="text-xs mt-0.5" style={{ color: '#6b7a88' }}>
                              {payment.purpose?.substring(0, 30)}...
                            </p>
                            <div className="flex items-center gap-1.5 mt-1">
                              <MethodBadge method={payment.payment_method} />
                              <span className="text-xs" style={{ color: '#6b7a88' }}>
                                {formatShortDate(payment.created_at)}
                              </span>
                            </div>
                          </div>
                        </div>
                        <div className="text-right">
                          <span className="text-xs font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(payment.amount)}
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          </>
        )}

        {/* TRANSACTIONS TAB - Only Paid */}
        {activeTab === 'transactions' && (
          <div className="bg-white rounded-xl border overflow-hidden" style={{ borderColor: '#eaeef2' }}>
            <div className="p-5 border-b" style={{ borderColor: '#eaeef2' }}>
              <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                <div>
                  <h3 className="text-base font-semibold" style={{ color: '#1a2634' }}>
                    Payment Transactions
                  </h3>
                  <p className="text-xs mt-0.5" style={{ color: '#6b7a88' }}>
                    Showing {payments.length} successful transactions
                  </p>
                </div>
                
                <div className="flex items-center gap-2">
                  <div className="relative">
                    <Search className="absolute left-2 top-1/2 transform -translate-y-1/2 w-3.5 h-3.5" style={{ color: '#6b7a88' }} />
                    <input
                      type="text"
                      placeholder="Search..."
                      value={filters.search}
                      onChange={(e) => handleFilterChange('search', e.target.value)}
                      className="pl-8 pr-3 py-1.5 border rounded-lg text-xs w-48"
                      style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                    />
                  </div>
                  
                  <button
                    onClick={() => setShowFilters(!showFilters)}
                    className="px-3 py-1.5 border rounded-lg flex items-center gap-1.5 hover:bg-gray-50 transition-colors text-xs"
                    style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                  >
                    <Sliders className="w-3.5 h-3.5" />
                    Filters
                    {showFilters ? <ChevronUp className="w-3.5 h-3.5" /> : <ChevronDown className="w-3.5 h-3.5" />}
                  </button>
                </div>
              </div>
              
              {showFilters && (
                <div className="mt-4 p-4 rounded-lg" style={{ backgroundColor: '#f0f2f4' }}>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <select
                      value={filters.payment_method}
                      onChange={(e) => handleFilterChange('payment_method', e.target.value)}
                      className="px-2 py-1.5 border rounded-lg text-xs bg-white"
                      style={{ borderColor: '#eaeef2' }}
                    >
                      <option value="all">All Methods</option>
                      <option value="gcash">GCash</option>
                      <option value="maya">Maya</option>
                      <option value="card">Card</option>
                    </select>
                    
                    <select
                      value={filters.client_system}
                      onChange={(e) => handleFilterChange('client_system', e.target.value)}
                      className="px-2 py-1.5 border rounded-lg text-xs bg-white"
                      style={{ borderColor: '#eaeef2' }}
                    >
                      <option value="all">All Systems</option>
                      {uniqueSystems.map(system => (
                        <option key={system} value={system}>
                          {getSystemConfig(system).label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              )}
            </div>
            
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr style={{ backgroundColor: '#f0f2f4' }}>
                    <th className="px-4 py-3 text-left font-semibold" style={{ color: '#6b7a88' }}>Transaction Details</th>
                    <th className="px-4 py-3 text-left font-semibold" style={{ color: '#6b7a88' }}>Amount</th>
                    <th className="px-4 py-3 text-left font-semibold" style={{ color: '#6b7a88' }}>Method</th>
                    <th className="px-4 py-3 text-left font-semibold" style={{ color: '#6b7a88' }}>Date</th>
                  </tr>
                </thead>
                <tbody>
                  {payments.length > 0 ? (
                    payments.map((payment) => (
                      <tr key={payment.id} className="hover:bg-gray-50 border-b" style={{ borderColor: '#eaeef2' }}>
                        <td className="px-4 py-3">
                          <div className="space-y-1">
                            <div className="flex items-center gap-1.5">
                              <SystemBadge system={payment.client_system} />
                              <span className="text-xs font-mono" style={{ color: '#6b7a88' }}>
                                {payment.payment_id?.slice(-8)}
                              </span>
                            </div>
                            <p className="text-xs" style={{ color: '#1a2634' }}>
                              {payment.purpose?.substring(0, 40)}...
                            </p>
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-sm font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(payment.amount)}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <MethodBadge method={payment.payment_method} />
                        </td>
                        <td className="px-4 py-3">
                          <span style={{ color: '#6b7a88' }}>
                            {formatShortDate(payment.created_at)}
                          </span>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="4" className="px-4 py-12 text-center">
                        <Database className="w-12 h-12 mx-auto mb-2" style={{ color: '#6b7a88' }} strokeWidth={1.5} />
                        <p className="text-sm font-medium" style={{ color: '#1a2634' }}>No transactions found</p>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            
            {payments.length > 0 && (
              <div className="px-4 py-3 border-t flex items-center justify-between" style={{ borderColor: '#eaeef2' }}>
                <p className="text-xs" style={{ color: '#6b7a88' }}>
                  Showing {payments.length} of {displayStats.total_transactions}
                </p>
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                    disabled={currentPage === 1}
                    className="p-1 rounded border hover:bg-gray-50 disabled:opacity-50"
                    style={{ borderColor: '#eaeef2' }}
                  >
                    <ChevronLeft className="w-4 h-4" style={{ color: '#6b7a88' }} />
                  </button>
                  <span className="px-2 py-1 text-xs" style={{ color: '#1a2634' }}>
                    Page {currentPage}
                  </span>
                  <button
                    onClick={() => setCurrentPage(prev => prev + 1)}
                    className="p-1 rounded border hover:bg-gray-50"
                    style={{ borderColor: '#eaeef2' }}
                  >
                    <ChevronRightIcon className="w-4 h-4" style={{ color: '#6b7a88' }} />
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ANALYTICS TAB - Simplified */}
        {activeTab === 'analytics' && (
          <div className="space-y-5">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs font-medium mb-1" style={{ color: '#6b7a88' }}>Success Rate</p>
                    <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                      100%
                    </p>
                  </div>
                  <div className="p-2 rounded-lg" style={{ backgroundColor: '#4caf5010' }}>
                    <Target className="w-5 h-5" style={{ color: '#4caf50' }} />
                  </div>
                </div>
              </div>
              
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs font-medium mb-1" style={{ color: '#6b7a88' }}>Total Volume</p>
                    <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                      {formatCurrency(displayStats.total_amount)}
                    </p>
                  </div>
                  <div className="p-2 rounded-lg" style={{ backgroundColor: '#4a90e210' }}>
                    <DollarSign className="w-5 h-5" style={{ color: '#4a90e2' }} />
                  </div>
                </div>
              </div>
              
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs font-medium mb-1" style={{ color: '#6b7a88' }}>Total Transactions</p>
                    <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                      {formatNumber(displayStats.total_transactions)}
                    </p>
                  </div>
                  <div className="p-2 rounded-lg" style={{ backgroundColor: '#ff980010' }}>
                    <FileText className="w-5 h-5" style={{ color: '#ff9800' }} />
                  </div>
                </div>
              </div>
              
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-xs font-medium mb-1" style={{ color: '#6b7a88' }}>Systems Used</p>
                    <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                      {systemChartData.length}
                    </p>
                  </div>
                  <div className="p-2 rounded-lg" style={{ backgroundColor: '#9c27b010' }}>
                    <Layers className="w-5 h-5" style={{ color: '#9c27b0' }} />
                  </div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <h3 className="text-base font-semibold mb-4" style={{ color: '#1a2634' }}>
                  System Performance
                </h3>
                <div className="space-y-4 max-h-80 overflow-y-auto pr-2">
                  {systemChartData.map((system) => (
                    <div key={system.system} className="space-y-1.5">
                      <div className="flex justify-between items-center text-xs">
                        <div className="flex items-center gap-1.5">
                          <div className="w-2 h-2 rounded-full" style={{ backgroundColor: system.color }} />
                          <span className="font-medium" style={{ color: '#1a2634' }}>
                            {system.name}
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(system.amount)}
                          </span>
                          <span className="px-1.5 py-0.5 rounded-full text-xs" style={{ backgroundColor: `${system.color}10`, color: system.color }}>
                            {system.count} txns
                          </span>
                        </div>
                      </div>
                      <ProgressBar 
                        value={system.amount} 
                        max={displayStats.total_amount} 
                        color={system.color}
                        showLabel={false}
                      />
                    </div>
                  ))}
                </div>
              </div>

              <div className="bg-white rounded-xl border p-5" style={{ borderColor: '#eaeef2' }}>
                <h3 className="text-base font-semibold mb-4" style={{ color: '#1a2634' }}>
                  Payment Methods
                </h3>
                <div className="space-y-4">
                  {methodChartData.map((method) => (
                    <div key={method.name} className="space-y-1.5">
                      <div className="flex justify-between items-center text-xs">
                        <div className="flex items-center gap-1.5">
                          <div className="w-2 h-2 rounded-full" style={{ backgroundColor: method.color }} />
                          <span className="font-medium" style={{ color: '#1a2634' }}>
                            {method.name}
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(method.amount)}
                          </span>
                          <span className="px-1.5 py-0.5 rounded-full text-xs" style={{ backgroundColor: `${method.color}10`, color: method.color }}>
                            {method.value} txns
                          </span>
                        </div>
                      </div>
                      <ProgressBar 
                        value={method.amount} 
                        max={displayStats.total_amount} 
                        color={method.color}
                        showLabel={false}
                      />
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* SYSTEMS TAB - 100% DYNAMIC */}
        {activeTab === 'systems' && (
          <AllSystemsView 
            systems={allSystemsChartData} 
            loading={loading && allSystems.length === 0} 
          />
        )}

        {/* Footer */}
        <div className="text-center pt-4 border-t text-xs" style={{ borderColor: '#eaeef2', color: '#6b7a88' }}>
          <div className="flex items-center justify-center gap-3 flex-wrap">
            <span>Digital Payment Gateway</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>{formatCurrency(displayStats.total_amount)} collected</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>{allSystems.length} systems</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>Updated: {new Date().toLocaleTimeString()}</span>
          </div>
        </div>
      </div>
    </div>
  );
}

export { DigiDashboard };