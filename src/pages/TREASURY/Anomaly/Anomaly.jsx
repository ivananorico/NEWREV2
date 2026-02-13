import React, { useState, useEffect } from 'react';
import { 
  TrendingUp, TrendingDown, AlertTriangle, 
  DollarSign, Building, Store, Home,
  Calendar, Download, RefreshCw, Brain,
  Activity, Zap, Shield, Target,
  ArrowUp, ArrowDown, AlertCircle,
  CheckCircle, Clock, BarChart3,
  PieChart, Users, MapPin, Bell, 
  Settings, X, FileText, Map,
  Landmark, Info, Eye, AlertOctagon,
  ChevronRight, Filter, Search,
  CreditCard, Timer, Percent, Flag,
  ShoppingCart, Coffee, Utensils, Beef
} from 'lucide-react';

export default function Anomaly() {
  const [anomalies, setAnomalies] = useState({
    rpt: null,
    business: null,
    market: null
  });
  const [loading, setLoading] = useState(true);
  const [activeSystem, setActiveSystem] = useState('rpt');
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [lastUpdated, setLastUpdated] = useState(new Date());
  const [selectedAnomaly, setSelectedAnomaly] = useState(null);
  const [severityFilter, setSeverityFilter] = useState('all');
  const [typeFilter, setTypeFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [systemStats, setSystemStats] = useState({
    rpt: {},
    business: {},
    market: {}
  });

  // FIXED: API Base URL for both localhost and production
 // Find this section in your code (around line 30-40)
const isProduction = window.location.hostname.includes('goserveph.com');
const API_BASE = isProduction 
  ? "/backend/Treasury"  // ✅ CORRECT - matches your working URL
  : "http://localhost/revenue2/backend/Treasury";

  // Color palette
  const colors = {
    primary: '#2c7da0',      // Teal blue - RPT
    business: '#4a90e2',     // Blue - Business
    market: '#8fbc8f',       // Green - Market
    secondary: '#6c757d',    // Gray
    success: '#2d6a4f',      // Green
    warning: '#e9c46a',      // Yellow
    danger: '#e63946',       // Red
    background: '#f8f9fa'    // Off-white
  };

  useEffect(() => {
    fetchAnomalies();
    
    if (autoRefresh) {
      const interval = setInterval(fetchAnomalies, 60000);
      return () => clearInterval(interval);
    }
  }, [autoRefresh, activeSystem]);

  const fetchAnomalies = async () => {
    setLoading(true);
    try {
      let url = '';
      
      if (activeSystem === 'rpt') {
        url = `${API_BASE}/anomaly_detection.php?system=rpt&action=detect`;
      } else if (activeSystem === 'business') {
        url = `${API_BASE}/anomaly_detection.php?system=business&action=detect`;
      } else if (activeSystem === 'market') {
        url = `${API_BASE}/anomaly_detection.php?system=market&action=detect`;
      } else if (activeSystem === 'all') {
        url = `${API_BASE}/anomaly_detection.php?system=all&action=detect`;
      }
      
      console.log('Fetching from:', url); // Debug log
      
      const response = await fetch(url);
      const data = await response.json();
      
      if (activeSystem === 'all') {
        // Handle all systems response
        setAnomalies({
          rpt: data.rpt?.anomalies || [],
          business: data.business?.anomalies || [],
          market: data.market?.anomalies || []
        });
        setSystemStats({
          rpt: data.rpt?.quarterly_stats || data.rpt?.monthly_stats || {},
          business: data.business?.quarterly_stats || {},
          market: data.market?.monthly_stats || {}
        });
      } else {
        // Handle single system response
        setAnomalies(prev => ({
          ...prev,
          [activeSystem]: data.anomalies || []
        }));
        
        // Handle different stat names (quarterly_stats for RPT/Business, monthly_stats for Market)
        const stats = data.quarterly_stats || data.monthly_stats || {};
        setSystemStats(prev => ({
          ...prev,
          [activeSystem]: stats
        }));
      }
      
      setLastUpdated(new Date());
    } catch (error) {
      console.error('Error fetching anomalies:', error);
    } finally {
      setLoading(false);
    }
  };

  const getSeverityColor = (severity) => {
    switch(severity?.toLowerCase()) {
      case 'critical': 
        return 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800';
      case 'warning': 
        return 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800';
      default: 
        return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800';
    }
  };

  const getSeverityBadge = (severity) => {
    switch(severity?.toLowerCase()) {
      case 'critical': 
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
      case 'warning': 
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
      default: 
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
    }
  };

  const getSystemIcon = (system) => {
    switch(system) {
      case 'RPT': return <Home className="w-4 h-4" />;
      case 'Business': return <Building className="w-4 h-4" />;
      case 'Market': return <Store className="w-4 h-4" />;
      default: return <Activity className="w-4 h-4" />;
    }
  };

  const getTypeIcon = (type) => {
    if (!type) return <AlertTriangle className="w-5 h-5" />;
    
    if (type.includes('LATE') || type.includes('CHRONIC')) return <Timer className="w-5 h-5" />;
    if (type.includes('PATTERN')) return <Activity className="w-5 h-5" />;
    if (type.includes('DISCOUNT')) return <Percent className="w-5 h-5" />;
    if (type.includes('CLUSTER')) return <MapPin className="w-5 h-5" />;
    if (type.includes('CRITICAL')) return <AlertOctagon className="w-5 h-5" />;
    if (type.includes('MISSED')) return <Clock className="w-5 h-5" />;
    if (type.includes('CAPITAL')) return <DollarSign className="w-5 h-5" />;
    return <AlertTriangle className="w-5 h-5" />;
  };

  const getTypeColor = (type) => {
    if (!type) return 'text-yellow-600';
    if (type.includes('CRITICAL') || type.includes('CHRONIC')) return 'text-red-600 dark:text-red-400';
    if (type.includes('LATE')) return 'text-orange-600 dark:text-orange-400';
    if (type.includes('CLUSTER')) return 'text-purple-600 dark:text-purple-400';
    if (type.includes('DISCOUNT')) return 'text-green-600 dark:text-green-400';
    if (type.includes('PATTERN')) return 'text-blue-600 dark:text-blue-400';
    if (type.includes('CAPITAL')) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-yellow-600 dark:text-yellow-400';
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount || 0);
  };

  const formatNumber = (num) => {
    return new Intl.NumberFormat('en-PH').format(num || 0);
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    try {
      return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch {
      return dateString;
    }
  };

  const getCurrentAnomalies = () => {
    if (activeSystem === 'all') {
      // For all systems, flatten all anomalies with system label
      return [
        ...(anomalies.rpt || []).map(a => ({ ...a, system: 'RPT' })),
        ...(anomalies.business || []).map(a => ({ ...a, system: 'Business' })),
        ...(anomalies.market || []).map(a => ({ ...a, system: 'Market' }))
      ];
    }
    return anomalies[activeSystem] || [];
  };

  const getFilteredAnomalies = () => {
    const currentAnomalies = getCurrentAnomalies();
    let filtered = [...currentAnomalies];
    
    if (severityFilter !== 'all') {
      filtered = filtered.filter(a => a.severity?.toLowerCase() === severityFilter.toLowerCase());
    }
    
    if (typeFilter !== 'all') {
      filtered = filtered.filter(a => a.type?.includes(typeFilter));
    }
    
    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      filtered = filtered.filter(a => 
        (a.owner || a.business_name || a.renter || '')?.toLowerCase().includes(term) ||
        a.barangay?.toLowerCase().includes(term) ||
        (a.owner_code || a.applicant_id || a.renter_code || a.stall_no || '')?.toLowerCase().includes(term)
      );
    }
    
    return filtered.sort((a, b) => {
      if (a.severity === 'critical' && b.severity !== 'critical') return -1;
      if (a.severity !== 'critical' && b.severity === 'critical') return 1;
      return (b.days_late || 0) - (a.days_late || 0);
    });
  };

  const getAnomalyStats = () => {
    const currentAnomalies = getCurrentAnomalies();
    return {
      critical: currentAnomalies.filter(a => a.severity === 'critical').length,
      warning: currentAnomalies.filter(a => a.severity === 'warning').length,
      info: currentAnomalies.filter(a => a.severity === 'info').length,
      total: currentAnomalies.length
    };
  };

  const stats = getAnomalyStats();
  const filteredAnomalies = getFilteredAnomalies();

  // Render RPT Content
  const renderRPTContent = () => {
    const stats = systemStats.rpt || {};
    return (
      <>
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Current Quarter</p>
              <Calendar className="w-4 h-4 text-gray-400" />
            </div>
            <p className="text-2xl font-bold" style={{ color: colors.primary }}>
              {stats.current_quarter?.quarter || 'Q1'} {stats.current_quarter?.year || '2026'}
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.current_quarter?.total || 0} assessments
            </p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Collection Rate</p>
              <DollarSign className="w-4 h-4 text-green-600" />
            </div>
            <p className="text-2xl font-bold text-green-600">
              {stats.current_quarter?.collection_rate || 0}%
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.quarter_over_quarter_change > 0 ? '+' : ''}
              {stats.quarter_over_quarter_change || 0}% vs last quarter
            </p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Overdue</p>
              <AlertTriangle className="w-4 h-4 text-red-500" />
            </div>
            <p className="text-2xl font-bold text-red-600">
              {stats.current_quarter?.overdue || 0}
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.current_quarter?.overdue_rate || 0}% of total
            </p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Avg Days Late</p>
              <Timer className="w-4 h-4 text-orange-500" />
            </div>
            <p className="text-2xl font-bold text-orange-600">
              {stats.current_quarter?.avg_days_late || 0}
            </p>
            <p className="text-xs text-gray-500 mt-1">days</p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Total Penalties</p>
              <Percent className="w-4 h-4 text-purple-500" />
            </div>
            <p className="text-lg font-bold text-purple-600">
              {formatCurrency(stats.current_quarter?.total_penalties || 0)}
            </p>
            <p className="text-xs text-gray-500 mt-1">this quarter</p>
          </div>
        </div>

        {stats.top_late_barangays?.length > 0 && (
          <div className="mb-6 p-4 bg-white rounded-lg border">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <MapPin className="w-4 h-4" style={{ color: colors.primary }} />
              Top 5 Barangays with Highest Late Payment Rates
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
              {stats.top_late_barangays.map((barangay, idx) => (
                <div key={idx} className="p-2 bg-gray-50 rounded-lg">
                  <p className="font-medium text-sm">{barangay.barangay}</p>
                  <p className="text-xs text-gray-500">{barangay.overdue_count} of {barangay.total_payments} payments</p>
                  <p className="text-sm font-bold text-red-600">{barangay.overdue_rate}% late</p>
                </div>
              ))}
            </div>
          </div>
        )}
      </>
    );
  };

  // Render Business Tax Content
  const renderBusinessContent = () => {
    const stats = systemStats.business || {};
    return (
      <>
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Current Quarter</p>
              <Calendar className="w-4 h-4 text-gray-400" />
            </div>
            <p className="text-2xl font-bold" style={{ color: colors.business }}>
              {stats.current_quarter?.quarter || 'Q1'} {stats.current_quarter?.year || '2026'}
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.current_quarter?.total || 0} assessments
            </p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Collection Rate</p>
              <DollarSign className="w-4 h-4 text-green-600" />
            </div>
            <p className="text-2xl font-bold text-green-600">
              {stats.current_quarter?.collection_rate || 0}%
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.quarter_over_quarter_change > 0 ? '+' : ''}
              {stats.quarter_over_quarter_change || 0}% vs last quarter
            </p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Overdue</p>
              <AlertTriangle className="w-4 h-4 text-red-500" />
            </div>
            <p className="text-2xl font-bold text-red-600">
              {stats.current_quarter?.overdue || 0}
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.current_quarter?.overdue_rate || 0}% of total
            </p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Avg Days Late</p>
              <Timer className="w-4 h-4 text-orange-500" />
            </div>
            <p className="text-2xl font-bold text-orange-600">
              {stats.current_quarter?.avg_days_late || 0}
            </p>
            <p className="text-xs text-gray-500 mt-1">days</p>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Total Penalties</p>
              <Percent className="w-4 h-4 text-purple-500" />
            </div>
            <p className="text-lg font-bold text-purple-600">
              {formatCurrency(stats.current_quarter?.total_penalties || 0)}
            </p>
            <p className="text-xs text-gray-500 mt-1">this quarter</p>
          </div>
        </div>

        {stats.top_late_barangays?.length > 0 && (
          <div className="mb-6 p-4 bg-white rounded-lg border">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <MapPin className="w-4 h-4" style={{ color: colors.business }} />
              Top 5 Barangays with Highest Business Tax Delinquency
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
              {stats.top_late_barangays.map((barangay, idx) => (
                <div key={idx} className="p-2 bg-gray-50 rounded-lg">
                  <p className="font-medium text-sm">{barangay.barangay}</p>
                  <p className="text-xs text-gray-500">{barangay.overdue_count} of {barangay.total_payments} payments</p>
                  <p className="text-sm font-bold text-red-600">{barangay.overdue_rate}% late</p>
                  <p className="text-xs text-gray-500">Avg {barangay.avg_days_late} days late</p>
                </div>
              ))}
            </div>
          </div>
        )}

        {stats.business_types?.length > 0 && (
          <div className="mb-6 p-4 bg-white rounded-lg border">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <Building className="w-4 h-4" style={{ color: colors.business }} />
              Top Business Types
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
              {stats.business_types.map((type, idx) => (
                <div key={idx} className="p-2 bg-gray-50 rounded-lg">
                  <p className="font-medium text-sm">{type.business_nature}</p>
                  <p className="text-xs text-gray-500">{type.count} businesses</p>
                  <p className="text-sm font-bold" style={{ color: colors.business }}>
                    {formatCurrency(type.avg_tax)} avg
                  </p>
                </div>
              ))}
            </div>
          </div>
        )}
      </>
    );
  };

  // Render Market Rent Content
  const renderMarketContent = () => {
    const stats = systemStats.market || {};
    return (
      <>
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Current Month</p>
              <Calendar className="w-4 h-4 text-gray-400" />
            </div>
            <p className="text-2xl font-bold" style={{ color: colors.market }}>
              {stats.current_month?.month || 'February'} {stats.current_month?.year || '2026'}
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.current_month?.total || 0} billings
            </p>
          </div>

          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Collection Rate</p>
              <DollarSign className="w-4 h-4 text-green-600" />
            </div>
            <p className="text-2xl font-bold text-green-600">
              {stats.current_month?.collection_rate || 0}%
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.month_over_month_change > 0 ? '+' : ''}
              {stats.month_over_month_change || 0}% vs last month
            </p>
          </div>

          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Overdue</p>
              <AlertTriangle className="w-4 h-4 text-red-500" />
            </div>
            <p className="text-2xl font-bold text-red-600">
              {stats.current_month?.overdue || 0}
            </p>
            <p className="text-xs text-gray-500 mt-1">
              {stats.current_month?.overdue_rate || 0}% of total
            </p>
          </div>

          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Avg Days Late</p>
              <Timer className="w-4 h-4 text-orange-500" />
            </div>
            <p className="text-2xl font-bold text-orange-600">
              {stats.current_month?.avg_days_late || 0}
            </p>
            <p className="text-xs text-gray-500 mt-1">days</p>
          </div>

          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
              <p className="text-sm text-gray-500">Total Collection</p>
              <Store className="w-4 h-4 text-purple-500" />
            </div>
            <p className="text-lg font-bold text-purple-600">
              {formatCurrency(stats.current_month?.total_collection || 0)}
            </p>
            <p className="text-xs text-gray-500 mt-1">this month</p>
          </div>
        </div>

        {stats.top_late_barangays?.length > 0 && (
          <div className="mb-6 p-4 bg-white rounded-lg border">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <MapPin className="w-4 h-4" style={{ color: colors.market }} />
              Top 5 Barangays with Highest Rent Delinquency
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
              {stats.top_late_barangays.map((barangay, idx) => (
                <div key={idx} className="p-2 bg-gray-50 rounded-lg">
                  <p className="font-medium text-sm">{barangay.barangay}</p>
                  <p className="text-xs text-gray-500">{barangay.overdue_count} of {barangay.total_payments} payments</p>
                  <p className="text-sm font-bold text-red-600">{barangay.overdue_rate}% late</p>
                  <p className="text-xs text-gray-500">{barangay.stall_count} stalls</p>
                </div>
              ))}
            </div>
          </div>
        )}

        {stats.stall_classes?.length > 0 && (
          <div className="mb-6 p-4 bg-white rounded-lg border">
            <h3 className="text-sm font-semibold mb-3 flex items-center gap-2">
              <Store className="w-4 h-4" style={{ color: colors.market }} />
              Stall Class Payment Performance
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
              {stats.stall_classes.map((stallClass, idx) => (
                <div key={idx} className="p-2 bg-gray-50 rounded-lg">
                  <p className="font-medium text-sm">Class {stallClass.class_name}</p>
                  <p className="text-xs text-gray-500">{stallClass.occupied_stalls} active stalls</p>
                  <p className="text-sm font-bold" style={{ color: colors.market }}>
                    {stallClass.payment_rate}% paid
                  </p>
                  <p className="text-xs text-gray-500">{stallClass.avg_days_late} days late avg</p>
                </div>
              ))}
            </div>
          </div>
        )}
      </>
    );
  };

  return (
    <div 
      className="mx-1 mt-1 p-6 rounded-lg min-h-screen"
      style={{ 
        backgroundColor: colors.background,
        color: '#1e293b'
      }}
    >
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div 
            className="p-3 rounded-lg"
            style={{ backgroundColor: colors.primary }}
          >
            <Brain className="w-6 h-6 text-white" />
          </div>
          <div>
            <h1 
              className="text-2xl font-bold"
              style={{ color: colors.primary }}
            >
              Tax & Revenue Anomaly Detection System
            </h1>
            <p style={{ color: colors.secondary }}>
              {activeSystem === 'rpt' && 'Real Property Tax - Quarterly Payment Monitoring'}
              {activeSystem === 'business' && 'Business Tax - Quarterly Payment Monitoring'}
              {activeSystem === 'market' && 'Market Rent - Monthly Payment Monitoring'}
              {activeSystem === 'all' && 'All Systems - Unified Monitoring'}
            </p>
          </div>
        </div>
        
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2 text-sm">
            <div 
              className={`w-2 h-2 rounded-full ${autoRefresh ? 'animate-pulse' : ''}`}
              style={{ backgroundColor: autoRefresh ? colors.success : colors.secondary }}
            />
            <span style={{ color: colors.secondary }}>
              Last updated: {lastUpdated.toLocaleTimeString()}
            </span>
          </div>
          
          <button
            onClick={() => setAutoRefresh(!autoRefresh)}
            className="p-2 rounded-lg transition-colors"
            style={{ 
              backgroundColor: autoRefresh ? `${colors.success}20` : `${colors.secondary}20`,
              color: autoRefresh ? colors.success : colors.secondary
            }}
          >
            <RefreshCw className={`w-5 h-5 ${autoRefresh ? 'animate-spin' : ''}`} />
          </button>
          
          <button
            onClick={fetchAnomalies}
            className="p-2 rounded-lg transition-colors hover:opacity-80"
            style={{ 
              backgroundColor: `${colors.secondary}20`,
              color: colors.secondary
            }}
          >
            <RefreshCw className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* System Filters - ALL SYSTEMS LIVE */}
      <div className="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
        <button
          onClick={() => setActiveSystem('rpt')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 relative"
          style={{ 
            backgroundColor: activeSystem === 'rpt' ? colors.primary : `${colors.secondary}10`,
            color: activeSystem === 'rpt' ? 'white' : colors.secondary,
            border: activeSystem === 'rpt' ? 'none' : '1px solid ' + colors.secondary + '30'
          }}
        >
          <Home className="w-4 h-4" />
          Real Property Tax
          <span className="ml-1 px-1.5 py-0.5 text-xs bg-green-500 text-white rounded-full">
            Live
          </span>
        </button>
        
        <button
          onClick={() => setActiveSystem('business')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 relative"
          style={{ 
            backgroundColor: activeSystem === 'business' ? colors.business : `${colors.secondary}10`,
            color: activeSystem === 'business' ? 'white' : colors.business,
            border: activeSystem === 'business' ? 'none' : '1px solid ' + colors.business + '40'
          }}
        >
          <Building className="w-4 h-4" />
          Business Tax
          <span className="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
            Live
          </span>
        </button>
        
        <button
          onClick={() => setActiveSystem('market')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 relative"
          style={{ 
            backgroundColor: activeSystem === 'market' ? colors.market : `${colors.secondary}10`,
            color: activeSystem === 'market' ? 'white' : colors.market,
            border: activeSystem === 'market' ? 'none' : '1px solid ' + colors.market + '40'
          }}
        >
          <Store className="w-4 h-4" />
          Market Rent
          <span className="ml-1 px-1.5 py-0.5 text-xs bg-green-500 text-white rounded-full">
            Live
          </span>
        </button>
        
        <button
          onClick={() => setActiveSystem('all')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
          style={{ 
            backgroundColor: activeSystem === 'all' ? colors.secondary : `${colors.secondary}10`,
            color: activeSystem === 'all' ? 'white' : colors.secondary,
            border: activeSystem === 'all' ? 'none' : '1px solid ' + colors.secondary + '30'
          }}
        >
          <Activity className="w-4 h-4" />
          All Systems
        </button>
      </div>

      {/* Stats Cards for All Systems */}
      {activeSystem === 'all' && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500">Total Anomalies</p>
                <p className="text-2xl font-bold" style={{ color: colors.primary }}>{stats.total}</p>
              </div>
              <AlertTriangle className="w-8 h-8 text-yellow-500" />
            </div>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500">Critical</p>
                <p className="text-2xl font-bold text-red-600">{stats.critical}</p>
              </div>
              <AlertOctagon className="w-8 h-8 text-red-500" />
            </div>
          </div>
          <div className="bg-white rounded-lg border p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500">Warning</p>
                <p className="text-2xl font-bold text-yellow-600">{stats.warning}</p>
              </div>
              <AlertCircle className="w-8 h-8 text-yellow-500" />
            </div>
          </div>
        </div>
      )}

      {/* System Specific Content - ALL LIVE */}
      {activeSystem === 'rpt' && renderRPTContent()}
      {activeSystem === 'business' && renderBusinessContent()}
      {activeSystem === 'market' && renderMarketContent()}

      {/* Filters - Show for all systems */}
      {activeSystem !== 'all' && (
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-semibold flex items-center gap-2">
              <AlertTriangle className="w-5 h-5" style={{ color: colors.warning }} />
              {activeSystem === 'rpt' && 'RPT Anomalies'}
              {activeSystem === 'business' && 'Business Tax Anomalies'}
              {activeSystem === 'market' && 'Market Rent Anomalies'}
              <span 
                className="text-sm px-2 py-1 rounded-full"
                style={{ 
                  backgroundColor: `${colors.secondary}20`,
                  color: colors.secondary
                }}
              >
                {filteredAnomalies.length} found
              </span>
            </h2>
          </div>
          
          <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <div className="relative flex-1 md:flex-none md:w-64">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4" style={{ color: colors.secondary }} />
              <input
                type="text"
                placeholder={
                  activeSystem === 'rpt' ? "Search owner or barangay..." : 
                  activeSystem === 'business' ? "Search business or barangay..." : 
                  "Search stall, renter or barangay..."
                }
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-10 pr-4 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2"
                style={{ 
                  borderColor: colors.secondary + '40',
                  focusRingColor: activeSystem === 'rpt' ? colors.primary : 
                                  activeSystem === 'business' ? colors.business : 
                                  colors.market
                }}
              />
            </div>
            
            <select 
              value={severityFilter}
              onChange={(e) => setSeverityFilter(e.target.value)}
              className="text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
              style={{ 
                borderColor: colors.secondary + '40',
                backgroundColor: 'white'
              }}
            >
              <option value="all">All Severities</option>
              <option value="critical">Critical</option>
              <option value="warning">Warning</option>
              <option value="info">Info</option>
            </select>
            
            <select 
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2"
              style={{ 
                borderColor: colors.secondary + '40',
                backgroundColor: 'white'
              }}
            >
              <option value="all">All Types</option>
              <option value="LATE">Late Payments</option>
              <option value="CRITICAL">Critical Late</option>
              <option value="CHRONIC">Chronic Late</option>
              <option value="PATTERN">Pattern Change</option>
              <option value="DISCOUNT">Discount</option>
              <option value="CLUSTER">Geographic</option>
            </select>
          </div>
        </div>
      )}

      {/* Anomalies List */}
      {loading ? (
        <div className="flex items-center justify-center py-16">
          <div className="relative">
            <div 
              className="w-16 h-16 border-4 rounded-full animate-spin"
              style={{ 
                borderColor: `${colors.secondary}20`,
                borderTopColor: activeSystem === 'rpt' ? colors.primary : 
                              activeSystem === 'business' ? colors.business : 
                              activeSystem === 'market' ? colors.market : 
                              colors.primary
              }}
            ></div>
            <div className="absolute inset-0 flex items-center justify-center">
              <Brain className="w-6 h-6 animate-pulse" style={{ 
                color: activeSystem === 'rpt' ? colors.primary : 
                       activeSystem === 'business' ? colors.business : 
                       activeSystem === 'market' ? colors.market : 
                       colors.primary 
              }} />
            </div>
          </div>
        </div>
      ) : filteredAnomalies.length === 0 ? (
        <div 
          className="text-center py-16 rounded-lg"
          style={{ backgroundColor: `${colors.secondary}08` }}
        >
          <CheckCircle className="w-16 h-16 mx-auto mb-4" style={{ color: colors.success }} />
          <p className="text-lg font-medium" style={{ color: colors.secondary }}>
            No {activeSystem === 'rpt' ? 'RPT' : activeSystem === 'business' ? 'business tax' : 'market rent'} anomalies detected
          </p>
          <p className="text-sm mt-1" style={{ color: colors.secondary }}>
            All {activeSystem === 'rpt' ? 'quarterly payments' : activeSystem === 'business' ? 'business tax payments' : 'monthly rent payments'} are processing normally
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {filteredAnomalies.map((anomaly, index) => (
            <div
              key={anomaly.id || index}
              className={`rounded-lg border p-5 hover:shadow-lg transition-all cursor-pointer
                ${getSeverityColor(anomaly.severity)}`}
              onClick={() => setSelectedAnomaly(selectedAnomaly?.id === anomaly.id ? null : anomaly)}
            >
              <div className="flex items-start gap-4">
                <div 
                  className="p-3 rounded-lg flex-shrink-0"
                  style={{ 
                    backgroundColor: anomaly.severity === 'critical' ? '#fee2e2' :
                                   anomaly.severity === 'warning' ? '#fef9c3' :
                                   anomaly.system === 'RPT' ? `${colors.primary}20` : 
                                   anomaly.system === 'Business' ? `${colors.business}20` : 
                                   anomaly.system === 'Market' ? `${colors.market}20` : 
                                   `${colors.primary}20`
                  }}
                >
                  {getTypeIcon(anomaly.type)}
                </div>
                
                <div className="flex-1 min-w-0">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1">
                      <div className="flex flex-wrap items-center gap-2 mb-2">
                        <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${getSeverityBadge(anomaly.severity)}`}>
                          {anomaly.severity?.toUpperCase()}
                        </span>
                        {anomaly.system && (
                          <span className="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700 flex items-center gap-1">
                            {getSystemIcon(anomaly.system)}
                            {anomaly.system}
                          </span>
                        )}
                        <span className={`text-sm font-medium ${getTypeColor(anomaly.type)}`}>
                          {anomaly.type?.replace(/_/g, ' ')}
                        </span>
                        {anomaly.quarter && (
                          <span className="flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            <Calendar className="w-3 h-3" />
                            {anomaly.quarter} {anomaly.year}
                          </span>
                        )}
                        {anomaly.month && (
                          <span className="flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            <Calendar className="w-3 h-3" />
                            Month {anomaly.month}/{anomaly.year}
                          </span>
                        )}
                        {anomaly.barangay && (
                          <span className="flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            <MapPin className="w-3 h-3" />
                            {anomaly.barangay}
                          </span>
                        )}
                      </div>
                      
                      <h3 className="font-semibold text-base mb-2">
                        {anomaly.title || anomaly.description}
                      </h3>
                      
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 mt-1 text-sm">
                        {/* Business Name */}
                        {anomaly.business_name && (
                          <span className="flex items-center gap-1 font-medium" style={{ 
                            color: anomaly.system === 'Business' ? colors.business : 
                                   anomaly.system === 'Market' ? colors.market : 
                                   colors.secondary 
                          }}>
                            {anomaly.system === 'Market' ? <Store className="w-4 h-4" /> : <Building className="w-4 h-4" />}
                            {anomaly.business_name}
                          </span>
                        )}
                        
                        {/* Renter Name (Market) */}
                        {anomaly.renter && (
                          <span className="flex items-center gap-1" style={{ color: colors.secondary }}>
                            <Users className="w-4 h-4" />
                            {anomaly.renter}
                          </span>
                        )}
                        
                        {/* Owner Name (RPT/Business) */}
                        {anomaly.owner && !anomaly.renter && (
                          <span className="flex items-center gap-1" style={{ color: colors.secondary }}>
                            <Users className="w-4 h-4" />
                            {anomaly.owner}
                          </span>
                        )}
                        
                        {/* Stall Number (Market) */}
                        {anomaly.stall_no && (
                          <span className="flex items-center gap-1 font-mono text-xs" style={{ color: colors.secondary }}>
                            <Store className="w-4 h-4" />
                            {anomaly.stall_no}
                          </span>
                        )}
                        
                        {/* Stall Class (Market) */}
                        {anomaly.stall_class && (
                          <span className="flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                            Class {anomaly.stall_class}
                          </span>
                        )}
                        
                        {/* Days Late */}
                        {anomaly.days_late && (
                          <span className="flex items-center gap-1 font-medium text-red-600">
                            <Timer className="w-4 h-4" />
                            {anomaly.days_late} days late
                          </span>
                        )}
                        
                        {/* Amount */}
                        {(anomaly.tax_amount || anomaly.rent_amount) && (
                          <span className="flex items-center gap-1" style={{ color: colors.secondary }}>
                            <DollarSign className="w-4 h-4" />
                            {formatCurrency(anomaly.tax_amount || anomaly.rent_amount)}
                          </span>
                        )}
                        
                        {/* Penalty */}
                        {anomaly.penalty_amount > 0 && (
                          <span className="flex items-center gap-1 font-medium text-orange-600">
                            <Percent className="w-4 h-4" />
                            Penalty: {formatCurrency(anomaly.penalty_amount)}
                          </span>
                        )}
                        
                        {/* Discount Rate */}
                        {anomaly.discount_rate && (
                          <span className="flex items-center gap-1 text-green-600">
                            <Percent className="w-4 h-4" />
                            {anomaly.discount_rate}% discount rate
                          </span>
                        )}
                        
                        {/* Chronic Late Rate */}
                        {anomaly.chronic_late_rate && (
                          <span className="flex items-center gap-1 text-red-600">
                            <AlertOctagon className="w-4 h-4" />
                            {anomaly.chronic_late_rate}% chronic late rate
                          </span>
                        )}
                      </div>
                      
                      {/* AI Analysis */}
                      {anomaly.ai_analysis && (
                        <div 
                          className="mt-4 p-3 rounded-lg border text-sm"
                          style={{ 
                            backgroundColor: anomaly.system === 'RPT' ? `${colors.primary}08` : 
                                          anomaly.system === 'Business' ? `${colors.business}08` : 
                                          anomaly.system === 'Market' ? `${colors.market}08` : 
                                          `${colors.primary}08`,
                            borderColor: anomaly.system === 'RPT' ? colors.primary + '40' : 
                                        anomaly.system === 'Business' ? colors.business + '40' : 
                                        anomaly.system === 'Market' ? colors.market + '40' : 
                                        colors.primary + '40'
                          }}
                        >
                          <div className="flex items-start gap-2">
                            <Brain className="w-4 h-4 mt-0.5 flex-shrink-0" style={{ 
                              color: anomaly.system === 'RPT' ? colors.primary : 
                                    anomaly.system === 'Business' ? colors.business : 
                                    anomaly.system === 'Market' ? colors.market : 
                                    colors.primary 
                            }} />
                            <p className="text-sm" style={{ 
                              color: anomaly.system === 'RPT' ? colors.primary : 
                                    anomaly.system === 'Business' ? colors.business : 
                                    anomaly.system === 'Market' ? colors.market : 
                                    colors.primary 
                            }}>
                              {anomaly.ai_analysis}
                            </p>
                          </div>
                        </div>
                      )}
                      
                      {/* Recommendation */}
                      {anomaly.recommendation && (
                        <div className="mt-3 flex items-start gap-2 text-sm">
                          <Shield className="w-4 h-4 mt-0.5 flex-shrink-0" style={{ color: colors.secondary }} />
                          <span style={{ color: colors.secondary }}>
                            <span className="font-medium">Recommendation:</span> {anomaly.recommendation}
                          </span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}