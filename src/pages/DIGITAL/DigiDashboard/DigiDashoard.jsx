import React, { useState, useEffect } from 'react';
import { 
  BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, 
  Tooltip, Legend, ResponsiveContainer, LineChart, Line,
  AreaChart, Area
} from 'recharts';
import { 
  CreditCard, DollarSign, Users, Calendar, AlertCircle, 
  TrendingUp, TrendingDown, RefreshCw, Download, Filter,
  CheckCircle, Clock, XCircle, FileText, 
  ArrowUpRight, ArrowDownRight, 
  BarChart as BarChartIcon, PieChart as PieChartIcon,
  LineChart as LineChartIcon, Database, Filter as FilterIcon,
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

// Enhanced color palette with ALL system colors
const COLORS = {
  // Primary brand colors
  primary: '#4a90e2',
  primaryLight: '#e8f0fe',
  primaryDark: '#2a5c8a',
  
  // Secondary colors
  secondary: '#9aa5b1',
  secondaryLight: '#f0f2f4',
  secondaryDark: '#6b7a88',
  
  // Status colors
  success: '#4caf50',
  successLight: '#e8f5e9',
  warning: '#ff9800',
  warningLight: '#fff3e0',
  danger: '#f44336',
  dangerLight: '#ffebee',
  
  // UI colors
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

// EXACT SYSTEMS FROM YOUR DATABASE - Based on client_system values in payment_transactions
const SYSTEM_CONFIG = {
  // RPT - Real Property Tax
  'rpt': { 
    bg: '#3f51b510', 
    color: '#3f51b5', 
    border: '#3f51b520',
    icon: Home, 
    label: 'Real Property Tax',
    category: 'Property Tax'
  },
  // Business Tax
  'business': { 
    bg: '#4caf5010', 
    color: '#4caf50', 
    border: '#4caf5020',
    icon: Building2, 
    label: 'Business Tax',
    category: 'Business Permits'
  },
  // Market Stall Rights
  'market': { 
    bg: '#ff980010', 
    color: '#ff9800', 
    border: '#ff980020',
    icon: Store, 
    label: 'Market Stall Rights',
    category: 'Market'
  },
  // Market Rent
  'market_rent': { 
    bg: '#9c27b010', 
    color: '#9c27b0', 
    border: '#9c27b020',
    icon: Calendar, 
    label: 'Market Rent',
    category: 'Market'
  },
  // TMM - Traffic Management
  'tmm': { 
    bg: '#2196f310', 
    color: '#2196f3', 
    border: '#2196f320',
    icon: Car, 
    label: 'Traffic Management',
    category: 'Traffic Violations'
  },
  // Zoning
  'zoning': { 
    bg: '#79554810', 
    color: '#795548', 
    border: '#79554820',
    icon: MapPin, 
    label: 'Zoning Clearance',
    category: 'Building & Planning'
  },
  // Sanitation
  'sanitation': { 
    bg: '#00968810', 
    color: '#009688', 
    border: '#00968820',
    icon: WaterDropIcon, 
    label: 'Sanitation',
    category: 'Health & Sanitation'
  },
  // WSS - Water Services
  'wss': { 
    bg: '#607d8b10', 
    color: '#607d8b', 
    border: '#607d8b20',
    icon: WaterDropIcon, 
    label: 'Water Services',
    category: 'Utilities'
  },
  // Franchise Application
  'franchise': { 
    bg: '#e91e6310', 
    color: '#e91e63', 
    border: '#e91e6320',
    icon: AwardIcon, 
    label: 'Franchise Application',
    category: 'Business Permits'
  },
  // Franchise Renewal
  'franchise_renewal': { 
    bg: '#ba68c810', 
    color: '#ba68c8', 
    border: '#ba68c820',
    icon: RefreshCw, 
    label: 'Franchise Renewal',
    category: 'Business Permits'
  },
  // Cemetery Services
  'cemetery': { 
    bg: '#607d8b10', 
    color: '#607d8b', 
    border: '#607d8b20',
    icon: CrossIcon, 
    label: 'Cemetery Services',
    category: 'Public Services'
  },
  // Default for any other systems
  'default': { 
    bg: '#9aa5b110', 
    color: '#9aa5b1', 
    border: '#9aa5b120',
    icon: Package, 
    label: 'Other Services',
    category: 'Other'
  }
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

// Status Badge Component
const StatusBadge = ({ status }) => {
  const config = {
    paid: {
      bg: '#e8f5e9',
      color: '#4caf50',
      border: '#4caf5020',
      icon: CheckCircle2,
      text: 'Paid'
    },
    pending: {
      bg: '#fff3e0',
      color: '#ff9800',
      border: '#ff980020',
      icon: Clock,
      text: 'Pending'
    },
    failed: {
      bg: '#ffebee',
      color: '#f44336',
      border: '#f4433620',
      icon: AlertOctagon,
      text: 'Failed'
    }
  };

  const { bg, color, border, icon: Icon, text } = config[status] || config.pending;

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

// System Badge Component - Uses EXACT systems from your DB
const SystemBadge = ({ system }) => {
  const config = SYSTEM_CONFIG[system] || SYSTEM_CONFIG.default;
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
const StatCard = ({ title, value, icon: Icon, trend, trendValue, color, subtitle, loading }) => {
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
      
      {trend && (
        <div className="flex items-center gap-2 mt-4">
          <span 
            className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            style={{ 
              backgroundColor: trend > 0 ? '#e8f5e9' : '#ffebee',
              color: trend > 0 ? '#4caf50' : '#f44336'
            }}
          >
            {trend > 0 ? <ArrowUp className="w-3 h-3 mr-1" /> : <ArrowDown className="w-3 h-3 mr-1" />}
            {Math.abs(trend)}%
          </span>
          <span className="text-xs" style={{ color: '#6b7a88' }}>
            {trendValue}
          </span>
        </div>
      )}
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

// Main Dashboard Component
export default function DigiDashboard() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [payments, setPayments] = useState([]);
  const [stats, setStats] = useState(null);
  const [exportLoading, setExportLoading] = useState(false);
  const [dateRange, setDateRange] = useState({
    startDate: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
    endDate: new Date().toISOString().split('T')[0]
  });
  const [filters, setFilters] = useState({
    payment_method: 'all',
    payment_status: 'all',
    client_system: 'all',
    search: ''
  });
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(15);
  const [chartType, setChartType] = useState('area');
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
        fetchStats()
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
      if (filters.payment_status !== 'all') {
        url += `&payment_status=${filters.payment_status}`;
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

  const exportToExcel = () => {
    setExportLoading(true);
    try {
      const wb = XLSX.utils.book_new();
      
      const wsPayments = XLSX.utils.json_to_sheet(payments.map(p => ({
        'Payment ID': p.payment_id,
        'System': SYSTEM_CONFIG[p.client_system]?.label || p.client_system,
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
      
      if (stats) {
        const wsStats = XLSX.utils.json_to_sheet([
          {
            'Total Transactions': stats.total_transactions,
            'Total Amount': stats.total_amount,
            'Paid Count': stats.paid_count,
            'Pending Count': stats.pending_count,
            'Failed Count': stats.failed_count,
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

  // Get unique systems from payments data
  const getUniqueSystems = () => {
    const systems = new Set();
    payments.forEach(p => {
      if (p.client_system) systems.add(p.client_system);
    });
    return Array.from(systems).sort();
  };

  // Get system chart data from actual payments
  const getSystemChartData = () => {
    const systemMap = new Map();
    
    payments.forEach(payment => {
      const system = payment.client_system;
      const amount = parseFloat(payment.amount) || 0;
      const status = payment.payment_status;
      
      if (!system) return;
      
      if (!systemMap.has(system)) {
        systemMap.set(system, {
          system: system,
          name: SYSTEM_CONFIG[system]?.label || system,
          count: 0,
          amount: 0,
          paidAmount: 0,
          pendingAmount: 0,
          failedAmount: 0,
          color: SYSTEM_CONFIG[system]?.color || '#9aa5b1'
        });
      }
      
      const data = systemMap.get(system);
      data.count += 1;
      data.amount += amount;
      
      if (status === 'paid') data.paidAmount += amount;
      if (status === 'pending') data.pendingAmount += amount;
      if (status === 'failed') data.failedAmount += amount;
    });
    
    return Array.from(systemMap.values()).sort((a, b) => b.amount - a.amount);
  };

  // Get daily trend data
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
          count: 0,
          paidAmount: 0
        });
      }
      
      const data = dailyMap.get(date);
      data.amount += parseFloat(payment.amount) || 0;
      data.count += 1;
      if (payment.payment_status === 'paid') {
        data.paidAmount += parseFloat(payment.amount) || 0;
      }
    });
    
    return Array.from(dailyMap.values()).sort((a, b) => 
      new Date(a.fullDate) - new Date(b.fullDate)
    );
  };

  // Get method chart data
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

  // Calculate summary stats from payments
  const calculateStats = () => {
    const total = payments.length;
    const paid = payments.filter(p => p.payment_status === 'paid').length;
    const pending = payments.filter(p => p.payment_status === 'pending').length;
    const failed = payments.filter(p => p.payment_status === 'failed').length;
    const totalAmount = payments.reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
    const paidAmount = payments.filter(p => p.payment_status === 'paid')
      .reduce((sum, p) => sum + (parseFloat(p.amount) || 0), 0);
    
    return {
      total_transactions: total,
      paid_count: paid,
      pending_count: pending,
      failed_count: failed,
      total_amount: totalAmount,
      paid_amount: paidAmount,
      success_rate: total > 0 ? ((paid / total) * 100).toFixed(1) : 0,
      average_amount: paid > 0 ? paidAmount / paid : 0
    };
  };

  const displayStats = stats || calculateStats();
  const systemChartData = getSystemChartData();
  const dailyTrendData = getDailyTrendData();
  const methodChartData = getMethodChartData();
  const uniqueSystems = getUniqueSystems();

  if (loading && payments.length === 0) {
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
      {/* Header Section */}
      <div className="border-b bg-white sticky top-0 z-10" style={{ borderColor: '#eaeef2' }}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          {/* Header Top Row */}
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6">
            <div className="flex items-center gap-4">
              <div 
                className="p-3 rounded-2xl"
                style={{ backgroundColor: '#4a90e210' }}
              >
                <Cloud2 className="w-8 h-8" style={{ color: '#4a90e2' }} strokeWidth={1.5} />
              </div>
              <div>
                <h1 className="text-2xl font-bold flex items-center gap-2" style={{ color: '#1a2634' }}>
                  Digital Payment Gateway
                  <span className="px-2.5 py-1 text-xs font-medium rounded-full" 
                        style={{ backgroundColor: '#e8f5e9', color: '#4caf50' }}>
                    Live
                  </span>
                </h1>
                <div className="flex items-center gap-3 mt-1">
                  <div className="flex items-center gap-1.5 text-sm" style={{ color: '#6b7a88' }}>
                    <CalendarIcon className="w-4 h-4" />
                    <span>{dateRange.startDate} - {dateRange.endDate}</span>
                  </div>
                  <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
                  <div className="flex items-center gap-1.5 text-sm" style={{ color: '#6b7a88' }}>
                    <Database className="w-4 h-4" />
                    <span>{formatNumber(payments.length)} transactions</span>
                  </div>
                  <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
                  <div className="flex items-center gap-1.5 text-sm" style={{ color: '#6b7a88' }}>
                    <Layers className="w-4 h-4" />
                    <span>{uniqueSystems.length} systems</span>
                  </div>
                </div>
              </div>
            </div>
            
            <div className="flex items-center gap-3">
              {/* Date Range Picker */}
              <div className="flex items-center border rounded-xl bg-white" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-center divide-x" style={{ borderColor: '#eaeef2' }}>
                  <input
                    type="date"
                    value={dateRange.startDate}
                    onChange={(e) => handleDateChange('startDate', e.target.value)}
                    className="px-4 py-2.5 text-sm border-0 focus:ring-0 focus:outline-none rounded-l-xl"
                    style={{ color: '#1a2634' }}
                  />
                  <input
                    type="date"
                    value={dateRange.endDate}
                    onChange={(e) => handleDateChange('endDate', e.target.value)}
                    className="px-4 py-2.5 text-sm border-0 focus:ring-0 focus:outline-none rounded-r-xl"
                    style={{ color: '#1a2634' }}
                  />
                </div>
              </div>
              
              {/* Export Button */}
              <button
                onClick={exportToExcel}
                disabled={exportLoading}
                className="px-5 py-2.5 rounded-xl flex items-center gap-2 transition-all hover:opacity-90"
                style={{ backgroundColor: '#4a90e2', color: 'white' }}
              >
                {exportLoading ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
                    <span>Exporting...</span>
                  </>
                ) : (
                  <>
                    <Download className="w-4 h-4" />
                    <span>Export Report</span>
                  </>
                )}
              </button>
              
              {/* Refresh Button */}
              <button
                onClick={fetchAllData}
                className="p-2.5 rounded-xl border hover:bg-gray-50 transition-all"
                style={{ borderColor: '#eaeef2', color: '#6b7a88' }}
              >
                <RefreshCw className="w-5 h-5" />
              </button>
            </div>
          </div>
          
          {/* Navigation Tabs */}
          <div className="flex items-center justify-between">
            <nav className="flex space-x-1">
              {[
                { id: 'overview', label: 'Overview', icon: BarChart3 },
                { id: 'transactions', label: 'Transactions', icon: FileText },
                { id: 'analytics', label: 'Analytics', icon: TrendingUpIcon2 },
                { id: 'systems', label: 'All Systems', icon: Layers }
              ].map((tab) => {
                const Icon = tab.icon;
                const isActive = activeTab === tab.id;
                
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`px-4 py-2.5 rounded-xl flex items-center gap-2 text-sm font-medium transition-all`}
                    style={{ 
                      backgroundColor: isActive ? '#4a90e210' : 'transparent',
                      color: isActive ? '#4a90e2' : '#6b7a88'
                    }}
                  >
                    <Icon className="w-4 h-4" strokeWidth={2} />
                    {tab.label}
                  </button>
                );
              })}
            </nav>
            
            {/* Time Range Quick Select */}
            <div className="flex items-center gap-1 p-1 rounded-xl" style={{ backgroundColor: '#f0f2f4' }}>
              {['day', 'week', 'month', 'year'].map((range) => (
                <button
                  key={range}
                  onClick={() => setTimeRange(range)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-medium capitalize transition-all`}
                  style={{ 
                    backgroundColor: timeRange === range ? 'white' : 'transparent',
                    color: timeRange === range ? '#4a90e2' : '#6b7a88',
                    boxShadow: timeRange === range ? '0 2px 8px rgba(0,0,0,0.04)' : 'none'
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
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {/* OVERVIEW TAB */}
        {activeTab === 'overview' && (
          <>
            {/* Key Metrics Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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
                subtitle={`${displayStats.success_rate}% success rate`}
              />
              
              <StatCard
                title="Pending"
                value={formatNumber(displayStats.pending_count)}
                icon={Clock}
                color="#ff9800"
                subtitle="Awaiting confirmation"
              />
              
              <StatCard
                title="Average Transaction"
                value={formatCurrency(displayStats.average_amount)}
                icon={TrendingUpIcon}
                color="#2196f3"
              />
            </div>

            {/* Revenue Overview & System Distribution */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Daily Revenue Chart */}
              <div className="lg:col-span-2 bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex justify-between items-center mb-6">
                  <div>
                    <h3 className="text-lg font-semibold flex items-center gap-2" style={{ color: '#1a2634' }}>
                      <Activity className="w-5 h-5" style={{ color: '#4a90e2' }} />
                      Revenue Trend
                    </h3>
                    <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                      Daily transaction volume and amount
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="flex p-1 rounded-lg" style={{ backgroundColor: '#f0f2f4' }}>
                      {['bar', 'line', 'area'].map((type) => (
                        <button
                          key={type}
                          onClick={() => setChartType(type)}
                          className={`px-3 py-1.5 rounded-lg text-xs font-medium capitalize transition-all`}
                          style={{ 
                            backgroundColor: chartType === type ? 'white' : 'transparent',
                            color: chartType === type ? '#4a90e2' : '#6b7a88',
                            boxShadow: chartType === type ? '0 2px 4px rgba(0,0,0,0.02)' : 'none'
                          }}
                        >
                          {type}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
                
                <div className="h-80">
                  {dailyTrendData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      {chartType === 'bar' ? (
                        <BarChart data={dailyTrendData}>
                          <CartesianGrid strokeDasharray="3 3" stroke="#eaeef2" vertical={false} />
                          <XAxis 
                            dataKey="date" 
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                          />
                          <YAxis 
                            yAxisId="left"
                            orientation="left"
                            stroke="#4a90e2"
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                            tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                          />
                          <YAxis 
                            yAxisId="right"
                            orientation="right"
                            stroke="#4caf50"
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                          />
                          <Tooltip content={<CustomTooltip />} />
                          <Legend 
                            verticalAlign="top" 
                            height={36}
                            iconType="circle"
                            iconSize={8}
                          />
                          <Bar 
                            yAxisId="left"
                            dataKey="amount" 
                            name="Amount" 
                            fill="#4a90e2"
                            radius={[4, 4, 0, 0]}
                            barSize={24}
                          />
                          <Bar 
                            yAxisId="right"
                            dataKey="count" 
                            name="Transactions" 
                            fill="#4caf50"
                            radius={[4, 4, 0, 0]}
                            barSize={24}
                          />
                        </BarChart>
                      ) : chartType === 'line' ? (
                        <LineChart data={dailyTrendData}>
                          <CartesianGrid strokeDasharray="3 3" stroke="#eaeef2" vertical={false} />
                          <XAxis 
                            dataKey="date" 
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                          />
                          <YAxis 
                            yAxisId="left"
                            orientation="left"
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                            tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                          />
                          <YAxis 
                            yAxisId="right"
                            orientation="right"
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                          />
                          <Tooltip content={<CustomTooltip />} />
                          <Legend 
                            verticalAlign="top" 
                            height={36}
                            iconType="circle"
                            iconSize={8}
                          />
                          <Line 
                            yAxisId="left"
                            type="monotone" 
                            dataKey="amount" 
                            name="Amount" 
                            stroke="#4a90e2"
                            strokeWidth={2.5}
                            dot={{ r: 4, fill: '#4a90e2', strokeWidth: 2, stroke: 'white' }}
                            activeDot={{ r: 6, fill: '#4a90e2', strokeWidth: 2, stroke: 'white' }}
                          />
                          <Line 
                            yAxisId="right"
                            type="monotone" 
                            dataKey="count" 
                            name="Transactions" 
                            stroke="#4caf50"
                            strokeWidth={2.5}
                            dot={{ r: 4, fill: '#4caf50', strokeWidth: 2, stroke: 'white' }}
                            activeDot={{ r: 6, fill: '#4caf50', strokeWidth: 2, stroke: 'white' }}
                          />
                        </LineChart>
                      ) : (
                        <AreaChart data={dailyTrendData}>
                          <defs>
                            <linearGradient id="amountGradient" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="5%" stopColor="#4a90e2" stopOpacity={0.2}/>
                              <stop offset="95%" stopColor="#4a90e2" stopOpacity={0}/>
                            </linearGradient>
                          </defs>
                          <CartesianGrid strokeDasharray="3 3" stroke="#eaeef2" vertical={false} />
                          <XAxis 
                            dataKey="date" 
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                          />
                          <YAxis 
                            axisLine={false}
                            tickLine={false}
                            tick={{ fill: '#6b7a88', fontSize: 12 }}
                            tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                          />
                          <Tooltip content={<CustomTooltip />} />
                          <Legend 
                            verticalAlign="top" 
                            height={36}
                            iconType="circle"
                            iconSize={8}
                          />
                          <Area 
                            type="monotone" 
                            dataKey="amount" 
                            name="Amount" 
                            stroke="#4a90e2"
                            strokeWidth={2.5}
                            fill="url(#amountGradient)"
                            dot={{ r: 4, fill: '#4a90e2', strokeWidth: 2, stroke: 'white' }}
                            activeDot={{ r: 6, fill: '#4a90e2', strokeWidth: 2, stroke: 'white' }}
                          />
                        </AreaChart>
                      )}
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full" style={{ color: '#6b7a88' }}>
                      <LineChartIcon className="w-12 h-12 mb-3" strokeWidth={1.5} />
                      <p className="font-medium">No transaction data available</p>
                      <p className="text-sm mt-1">Try adjusting your date range</p>
                    </div>
                  )}
                </div>
              </div>

              {/* System Distribution */}
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <h3 className="text-lg font-semibold flex items-center gap-2 mb-6" style={{ color: '#1a2634' }}>
                  <PieChartIcon className="w-5 h-5" style={{ color: '#9c27b0' }} />
                  Revenue by System
                </h3>
                
                <div className="h-48">
                  {systemChartData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                      <PieChart>
                        <Pie
                          data={systemChartData}
                          cx="50%"
                          cy="50%"
                          innerRadius={60}
                          outerRadius={80}
                          paddingAngle={4}
                          dataKey="amount"
                        >
                          {systemChartData.map((entry, index) => (
                            <Cell 
                              key={`cell-${index}`} 
                              fill={entry.color}
                              stroke="white"
                              strokeWidth={2}
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
                            borderRadius: '12px',
                            boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
                            padding: '8px 12px'
                          }}
                        />
                      </PieChart>
                    </ResponsiveContainer>
                  ) : (
                    <div className="flex flex-col items-center justify-center h-full" style={{ color: '#6b7a88' }}>
                      <PieChartIcon className="w-12 h-12 mb-3" strokeWidth={1.5} />
                      <p className="font-medium">No system data</p>
                    </div>
                  )}
                </div>

                <div className="mt-6 space-y-3 max-h-48 overflow-y-auto">
                  {systemChartData.slice(0, 5).map((system) => (
                    <div key={system.system} className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <div className="w-3 h-3 rounded-full" style={{ backgroundColor: system.color }} />
                        <span className="text-sm" style={{ color: '#6b7a88' }}>
                          {system.name}
                        </span>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="text-sm font-medium" style={{ color: '#1a2634' }}>
                          {formatCurrency(system.amount)}
                        </span>
                        <span className="text-xs" style={{ color: '#6b7a88' }}>
                          ({displayStats.total_amount ? ((system.amount / displayStats.total_amount) * 100).toFixed(1) : 0}%)
                        </span>
                      </div>
                    </div>
                  ))}
                  {systemChartData.length > 5 && (
                    <p className="text-xs text-center pt-2" style={{ color: '#6b7a88' }}>
                      +{systemChartData.length - 5} more systems
                    </p>
                  )}
                </div>
              </div>
            </div>

            {/* Payment Methods & Recent Transactions */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Payment Methods */}
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex justify-between items-center mb-6">
                  <div>
                    <h3 className="text-lg font-semibold" style={{ color: '#1a2634' }}>
                      Payment Methods
                    </h3>
                    <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                      Digital wallet usage
                    </p>
                  </div>
                  <Smartphone2 className="w-5 h-5" style={{ color: '#4a90e2' }} strokeWidth={1.5} />
                </div>

                <div className="space-y-5">
                  {methodChartData.length > 0 ? (
                    methodChartData.map((method) => (
                      <div key={method.name} className="p-4 rounded-xl" style={{ backgroundColor: `${method.color}10` }}>
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center gap-3">
                            <div className="p-2 rounded-lg bg-white">
                              {method.name === 'GCash' && <Smartphone2 className="w-5 h-5" style={{ color: method.color }} />}
                              {method.name === 'Maya' && <Wallet2 className="w-5 h-5" style={{ color: method.color }} />}
                              {method.name === 'Card' && <CreditCard2 className="w-5 h-5" style={{ color: method.color }} />}
                            </div>
                            <div>
                              <span className="font-medium" style={{ color: '#1a2634' }}>{method.name}</span>
                              <p className="text-xs mt-0.5" style={{ color: '#6b7a88' }}>
                                {formatNumber(method.value)} transactions
                              </p>
                            </div>
                          </div>
                          <span className="text-lg font-bold" style={{ color: method.color }}>
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
                    <div className="text-center py-8" style={{ color: '#6b7a88' }}>
                      <CreditCard2 className="w-12 h-12 mx-auto mb-3" strokeWidth={1.5} />
                      <p>No payment method data</p>
                    </div>
                  )}
                </div>
              </div>

              {/* Recent Transactions */}
              <div className="lg:col-span-2 bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex justify-between items-center mb-6">
                  <div>
                    <h3 className="text-lg font-semibold" style={{ color: '#1a2634' }}>
                      Recent Transactions
                    </h3>
                    <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                      Latest payment activities across all systems
                    </p>
                  </div>
                  <button 
                    onClick={() => setActiveTab('transactions')}
                    className="text-sm font-medium flex items-center gap-1 hover:gap-2 transition-all"
                    style={{ color: '#4a90e2' }}
                  >
                    View All
                    <ChevronRight className="w-4 h-4" />
                  </button>
                </div>

                <div className="space-y-3 max-h-96 overflow-y-auto">
                  {payments.slice(0, 8).map((payment) => (
                    <div 
                      key={payment.id} 
                      className="flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 transition-colors"
                    >
                      <div className="flex items-start gap-3">
                        <div 
                          className="p-2 rounded-lg"
                          style={{ backgroundColor: `${SYSTEM_CONFIG[payment.client_system]?.color || '#9aa5b1'}10` }}
                        >
                          {React.createElement(SYSTEM_CONFIG[payment.client_system]?.icon || Package, { 
                            className: "w-4 h-4", 
                            style: { color: SYSTEM_CONFIG[payment.client_system]?.color || '#9aa5b1' } 
                          })}
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-medium text-sm" style={{ color: '#1a2634' }}>
                              {SYSTEM_CONFIG[payment.client_system]?.label || payment.client_system}
                            </span>
                            <StatusBadge status={payment.payment_status} />
                          </div>
                          <p className="text-xs mt-1" style={{ color: '#6b7a88' }}>
                            {payment.purpose?.substring(0, 40)}...
                          </p>
                          <div className="flex items-center gap-2 mt-1.5">
                            <MethodBadge method={payment.payment_method} />
                            <span className="text-xs" style={{ color: '#6b7a88' }}>
                              {formatShortDate(payment.created_at)}
                            </span>
                          </div>
                        </div>
                      </div>
                      <div className="text-right">
                        <span className="text-sm font-bold" style={{ color: '#1a2634' }}>
                          {formatCurrency(payment.amount)}
                        </span>
                        {payment.paid_at && (
                          <p className="text-xs mt-1" style={{ color: '#4caf50' }}>
                            <CheckCircle className="w-3 h-3 inline mr-1" />
                            {formatShortDate(payment.paid_at)}
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </>
        )}

        {/* TRANSACTIONS TAB */}
        {activeTab === 'transactions' && (
          <div className="bg-white rounded-2xl border overflow-hidden" style={{ borderColor: '#eaeef2' }}>
            {/* Filters Bar */}
            <div className="p-6 border-b" style={{ borderColor: '#eaeef2' }}>
              <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                  <h3 className="text-lg font-semibold" style={{ color: '#1a2634' }}>
                    Payment Transactions
                  </h3>
                  <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                    Showing {payments.length} of {displayStats.total_transactions} total transactions
                  </p>
                </div>
                
                <div className="flex items-center gap-3">
                  {/* Search */}
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: '#6b7a88' }} />
                    <input
                      type="text"
                      placeholder="Search transactions..."
                      value={filters.search}
                      onChange={(e) => handleFilterChange('search', e.target.value)}
                      className="pl-10 pr-4 py-2.5 border rounded-xl text-sm w-64"
                      style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                    />
                  </div>
                  
                  {/* Filter Toggle */}
                  <button
                    onClick={() => setShowFilters(!showFilters)}
                    className="px-4 py-2.5 border rounded-xl flex items-center gap-2 hover:bg-gray-50 transition-colors"
                    style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                  >
                    <Sliders className="w-4 h-4" />
                    Filters
                    {showFilters ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                  </button>
                </div>
              </div>
              
              {/* Advanced Filters */}
              {showFilters && (
                <div className="mt-6 p-5 rounded-xl" style={{ backgroundColor: '#f0f2f4' }}>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-medium mb-2" style={{ color: '#6b7a88' }}>
                        Payment Method
                      </label>
                      <select
                        value={filters.payment_method}
                        onChange={(e) => handleFilterChange('payment_method', e.target.value)}
                        className="w-full px-3 py-2.5 border rounded-xl text-sm bg-white"
                        style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                      >
                        <option value="all">All Methods</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="card">Card</option>
                      </select>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium mb-2" style={{ color: '#6b7a88' }}>
                        Payment Status
                      </label>
                      <select
                        value={filters.payment_status}
                        onChange={(e) => handleFilterChange('payment_status', e.target.value)}
                        className="w-full px-3 py-2.5 border rounded-xl text-sm bg-white"
                        style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                      >
                        <option value="all">All Statuses</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                      </select>
                    </div>
                    
                    <div>
                      <label className="block text-xs font-medium mb-2" style={{ color: '#6b7a88' }}>
                        Client System
                      </label>
                      <select
                        value={filters.client_system}
                        onChange={(e) => handleFilterChange('client_system', e.target.value)}
                        className="w-full px-3 py-2.5 border rounded-xl text-sm bg-white"
                        style={{ borderColor: '#eaeef2', color: '#1a2634' }}
                      >
                        <option value="all">All Systems</option>
                        {uniqueSystems.map(system => (
                          <option key={system} value={system}>
                            {SYSTEM_CONFIG[system]?.label || system}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>
              )}
            </div>
            
            {/* Transactions Table */}
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr style={{ backgroundColor: '#f0f2f4' }}>
                    <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: '#6b7a88' }}>
                      Transaction Details
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: '#6b7a88' }}>
                      Amount
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: '#6b7a88' }}>
                      Method & Status
                    </th>
                    <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider" style={{ color: '#6b7a88' }}>
                      Date & Time
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {payments.length > 0 ? (
                    payments.map((payment) => (
                      <tr 
                        key={payment.id} 
                        className="hover:bg-gray-50 transition-colors border-b"
                        style={{ borderColor: '#eaeef2' }}
                      >
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            <div className="flex items-center gap-2">
                              <SystemBadge system={payment.client_system} />
                              <span className="text-xs font-mono" style={{ color: '#6b7a88' }}>
                                {payment.payment_id}
                              </span>
                            </div>
                            <p className="text-sm font-medium" style={{ color: '#1a2634' }}>
                              {payment.purpose}
                            </p>
                            <div className="flex items-center gap-2 text-xs" style={{ color: '#6b7a88' }}>
                              <Receipt className="w-3 h-3" />
                              {payment.receipt_number || 'No receipt'}
                              <span className="mx-1">•</span>
                              <Smartphone className="w-3 h-3" />
                              {payment.phone}
                            </div>
                            <p className="text-xs" style={{ color: '#6b7a88' }}>
                              Ref: {payment.client_reference}
                            </p>
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <span className="text-base font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(payment.amount)}
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-2">
                            <MethodBadge method={payment.payment_method} />
                            <StatusBadge status={payment.payment_status} />
                          </div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="space-y-1">
                            <div className="text-sm font-medium" style={{ color: '#1a2634' }}>
                              {formatDate(payment.created_at)}
                            </div>
                            {payment.paid_at && (
                              <div className="text-xs flex items-center gap-1" style={{ color: '#4caf50' }}>
                                <CheckCircle className="w-3 h-3" />
                                Paid: {formatShortDate(payment.paid_at)}
                              </div>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="4" className="px-6 py-16 text-center">
                        <div className="flex flex-col items-center">
                          <Database className="w-16 h-16 mb-4" style={{ color: '#6b7a88' }} strokeWidth={1.5} />
                          <p className="text-base font-medium" style={{ color: '#1a2634' }}>No transactions found</p>
                          <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                            Try adjusting your filters or date range
                          </p>
                        </div>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            
            {/* Pagination */}
            {payments.length > 0 && (
              <div className="px-6 py-4 border-t flex items-center justify-between" style={{ borderColor: '#eaeef2' }}>
                <p className="text-sm" style={{ color: '#6b7a88' }}>
                  Showing <span className="font-medium">{payments.length}</span> of{' '}
                  <span className="font-medium">{displayStats.total_transactions}</span> transactions
                </p>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                    disabled={currentPage === 1}
                    className="p-2 rounded-lg border hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    style={{ borderColor: '#eaeef2' }}
                  >
                    <ChevronLeft className="w-5 h-5" style={{ color: '#6b7a88' }} />
                  </button>
                  <span className="px-4 py-2 text-sm" style={{ color: '#1a2634' }}>
                    Page {currentPage}
                  </span>
                  <button
                    onClick={() => setCurrentPage(prev => prev + 1)}
                    className="p-2 rounded-lg border hover:bg-gray-50"
                    style={{ borderColor: '#eaeef2' }}
                  >
                    <ChevronRightIcon className="w-5 h-5" style={{ color: '#6b7a88' }} />
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ANALYTICS TAB */}
        {activeTab === 'analytics' && (
          <div className="space-y-6">
            {/* Performance Metrics */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm font-medium mb-1" style={{ color: '#6b7a88' }}>Success Rate</p>
                    <p className="text-3xl font-bold" style={{ color: '#1a2634' }}>
                      {displayStats.success_rate || 0}%
                    </p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: '#4caf5010' }}>
                    <Target className="w-6 h-6" style={{ color: '#4caf50' }} />
                  </div>
                </div>
                <div className="mt-4">
                  <ProgressBar 
                    value={displayStats.success_rate || 0} 
                    max={100} 
                    color="#4caf50"
                    label="Target: 95%"
                  />
                </div>
              </div>
              
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm font-medium mb-1" style={{ color: '#6b7a88' }}>Total Volume</p>
                    <p className="text-3xl font-bold" style={{ color: '#1a2634' }}>
                      {formatCurrency(displayStats.total_amount)}
                    </p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: '#4a90e210' }}>
                    <DollarSign className="w-6 h-6" style={{ color: '#4a90e2' }} />
                  </div>
                </div>
              </div>
              
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm font-medium mb-1" style={{ color: '#6b7a88' }}>Total Transactions</p>
                    <p className="text-3xl font-bold" style={{ color: '#1a2634' }}>
                      {formatNumber(displayStats.total_transactions)}
                    </p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: '#ff980010' }}>
                    <FileText className="w-6 h-6" style={{ color: '#ff9800' }} />
                  </div>
                </div>
              </div>
              
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-sm font-medium mb-1" style={{ color: '#6b7a88' }}>Systems Used</p>
                    <p className="text-3xl font-bold" style={{ color: '#1a2634' }}>
                      {uniqueSystems.length}
                    </p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: '#9c27b010' }}>
                    <Layers className="w-6 h-6" style={{ color: '#9c27b0' }} />
                  </div>
                </div>
              </div>
            </div>

            {/* System Performance Analytics */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* System Performance Chart */}
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <h3 className="text-lg font-semibold mb-6" style={{ color: '#1a2634' }}>
                  System Performance
                </h3>
                <div className="space-y-5 max-h-96 overflow-y-auto pr-2">
                  {systemChartData.map((system) => (
                    <div key={system.system} className="space-y-2">
                      <div className="flex justify-between items-center">
                        <div className="flex items-center gap-2">
                          <div className="w-3 h-3 rounded-full" style={{ backgroundColor: system.color }} />
                          <span className="text-sm font-medium" style={{ color: '#1a2634' }}>
                            {system.name}
                          </span>
                        </div>
                        <div className="flex items-center gap-3">
                          <span className="text-sm font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(system.amount)}
                          </span>
                          <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${system.color}10`, color: system.color }}>
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

              {/* Payment Methods Analytics */}
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <h3 className="text-lg font-semibold mb-6" style={{ color: '#1a2634' }}>
                  Payment Methods
                </h3>
                <div className="space-y-5">
                  {methodChartData.map((method) => (
                    <div key={method.name} className="space-y-2">
                      <div className="flex justify-between items-center">
                        <div className="flex items-center gap-2">
                          <div className="w-3 h-3 rounded-full" style={{ backgroundColor: method.color }} />
                          <span className="text-sm font-medium" style={{ color: '#1a2634' }}>
                            {method.name}
                          </span>
                        </div>
                        <div className="flex items-center gap-3">
                          <span className="text-sm font-bold" style={{ color: '#1a2634' }}>
                            {formatCurrency(method.amount)}
                          </span>
                          <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: `${method.color}10`, color: method.color }}>
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

            {/* Status Distribution */}
            <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
              <h3 className="text-lg font-semibold mb-6" style={{ color: '#1a2634' }}>
                Transaction Status
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="p-4 rounded-xl" style={{ backgroundColor: '#e8f5e9' }}>
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-white">
                      <CheckCircle2 className="w-5 h-5" style={{ color: '#4caf50' }} />
                    </div>
                    <div>
                      <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                        {formatNumber(displayStats.paid_count)}
                      </p>
                      <p className="text-sm" style={{ color: '#4caf50' }}>Paid</p>
                    </div>
                  </div>
                </div>
                <div className="p-4 rounded-xl" style={{ backgroundColor: '#fff3e0' }}>
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-white">
                      <Clock className="w-5 h-5" style={{ color: '#ff9800' }} />
                    </div>
                    <div>
                      <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                        {formatNumber(displayStats.pending_count)}
                      </p>
                      <p className="text-sm" style={{ color: '#ff9800' }}>Pending</p>
                    </div>
                  </div>
                </div>
                <div className="p-4 rounded-xl" style={{ backgroundColor: '#ffebee' }}>
                  <div className="flex items-center gap-3">
                    <div className="p-2 rounded-lg bg-white">
                      <AlertOctagon className="w-5 h-5" style={{ color: '#f44336' }} />
                    </div>
                    <div>
                      <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                        {formatNumber(displayStats.failed_count)}
                      </p>
                      <p className="text-sm" style={{ color: '#f44336' }}>Failed</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* SYSTEMS TAB - Shows ALL systems from your database */}
        {activeTab === 'systems' && (
          <div className="space-y-6">
            <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
              <div className="flex justify-between items-center mb-6">
                <div>
                  <h3 className="text-lg font-semibold" style={{ color: '#1a2634' }}>
                    All Connected Systems
                  </h3>
                  <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                    {uniqueSystems.length} systems with payment transactions
                  </p>
                </div>
                <div className="px-3 py-1.5 rounded-full text-xs font-medium" style={{ backgroundColor: '#4a90e210', color: '#4a90e2' }}>
                  <Layers className="w-4 h-4 inline mr-1" />
                  {uniqueSystems.length} Active
                </div>
              </div>

              {/* Systems Grid */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {systemChartData.map((system) => {
                  const config = SYSTEM_CONFIG[system.system] || SYSTEM_CONFIG.default;
                  const Icon = config.icon;
                  
                  return (
                    <div 
                      key={system.system} 
                      className="bg-white rounded-xl border p-5 hover:shadow-lg transition-all"
                      style={{ borderColor: `${system.color}30` }}
                    >
                      <div className="flex items-start justify-between mb-4">
                        <div className="flex items-center gap-3">
                          <div 
                            className="p-3 rounded-xl"
                            style={{ backgroundColor: `${system.color}10` }}
                          >
                            <Icon className="w-5 h-5" style={{ color: system.color }} />
                          </div>
                          <div>
                            <h4 className="font-semibold" style={{ color: '#1a2634' }}>
                              {system.name}
                            </h4>
                            <p className="text-xs mt-0.5" style={{ color: '#6b7a88' }}>
                              {config.category}
                            </p>
                          </div>
                        </div>
                        <span className="text-xs px-2 py-1 rounded-full" style={{ backgroundColor: '#e8f5e9', color: '#4caf50' }}>
                          Active
                        </span>
                      </div>
                      
                      <div className="space-y-3">
                        <div className="flex justify-between items-center">
                          <span className="text-sm" style={{ color: '#6b7a88' }}>Revenue</span>
                          <span className="text-lg font-bold" style={{ color: system.color }}>
                            {formatCurrency(system.amount)}
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-sm" style={{ color: '#6b7a88' }}>Transactions</span>
                          <span className="font-medium" style={{ color: '#1a2634' }}>
                            {formatNumber(system.count)}
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-sm" style={{ color: '#6b7a88' }}>Avg. Transaction</span>
                          <span className="font-medium" style={{ color: '#1a2634' }}>
                            {formatCurrency(system.count > 0 ? system.amount / system.count : 0)}
                          </span>
                        </div>
                        <div className="flex justify-between items-center">
                          <span className="text-sm" style={{ color: '#6b7a88' }}>Success Rate</span>
                          <span className="font-medium" style={{ color: system.count > 0 ? '#4caf50' : '#6b7a88' }}>
                            {system.count > 0 ? ((system.paidAmount / system.amount) * 100).toFixed(1) : 0}%
                          </span>
                        </div>
                      </div>
                      
                      <div className="mt-4 pt-4 border-t" style={{ borderColor: '#eaeef2' }}>
                        <ProgressBar 
                          value={system.amount} 
                          max={displayStats.total_amount} 
                          color={system.color}
                          label="Share of Total"
                        />
                      </div>
                    </div>
                  );
                })}
              </div>

              {/* If no systems found */}
              {systemChartData.length === 0 && (
                <div className="text-center py-12">
                  <Layers className="w-16 h-16 mx-auto mb-4" style={{ color: '#6b7a88' }} strokeWidth={1.5} />
                  <p className="text-base font-medium" style={{ color: '#1a2634' }}>No system data available</p>
                  <p className="text-sm mt-1" style={{ color: '#6b7a88' }}>
                    No payment transactions found for the selected period
                  </p>
                </div>
              )}
            </div>

            {/* System Summary Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <p className="text-sm font-medium mb-2" style={{ color: '#6b7a88' }}>Total Systems</p>
                <p className="text-3xl font-bold" style={{ color: '#1a2634' }}>{uniqueSystems.length}</p>
                <p className="text-xs mt-2" style={{ color: '#6b7a88' }}>Connected to payment gateway</p>
              </div>
              
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <p className="text-sm font-medium mb-2" style={{ color: '#6b7a88' }}>Most Active</p>
                <p className="text-xl font-bold" style={{ color: systemChartData[0]?.color || '#1a2634' }}>
                  {systemChartData[0]?.name || 'N/A'}
                </p>
                <p className="text-xs mt-2" style={{ color: '#6b7a88' }}>
                  {systemChartData[0] ? `${formatCurrency(systemChartData[0].amount)} collected` : ''}
                </p>
              </div>
              
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <p className="text-sm font-medium mb-2" style={{ color: '#6b7a88' }}>Total Revenue</p>
                <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                  {formatCurrency(displayStats.total_amount)}
                </p>
                <p className="text-xs mt-2" style={{ color: '#6b7a88' }}>
                  Across all systems
                </p>
              </div>
              
              <div className="bg-white rounded-2xl border p-6" style={{ borderColor: '#eaeef2' }}>
                <p className="text-sm font-medium mb-2" style={{ color: '#6b7a88' }}>Total Transactions</p>
                <p className="text-2xl font-bold" style={{ color: '#1a2634' }}>
                  {formatNumber(displayStats.total_transactions)}
                </p>
                <p className="text-xs mt-2" style={{ color: '#6b7a88' }}>
                  System-wide
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Footer */}
        <div className="text-center pt-6 border-t" style={{ borderColor: '#eaeef2' }}>
          <div className="flex items-center justify-center gap-6 text-sm" style={{ color: '#6b7a88' }}>
            <span>Digital Payment Gateway Dashboard</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>{formatCurrency(displayStats.total_amount)} collected</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>{formatNumber(displayStats.total_transactions)} transactions</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>{uniqueSystems.length} active systems</span>
            <span className="w-1 h-1 rounded-full" style={{ backgroundColor: '#eaeef2' }} />
            <span>Last updated: {new Date().toLocaleTimeString()}</span>
          </div>
        </div>
      </div>
    </div>
  );
}

export { DigiDashboard };