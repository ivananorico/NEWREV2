import React, { useState, useEffect, useCallback } from 'react';
import { 
  AlertTriangle, Activity, RefreshCw, Download, BarChart3, LineChart, 
  PieChart, Bell, CheckCircle, AlertCircle, Clock, DollarSign, Users, 
  Percent, Calendar, ChevronDown, Eye, EyeOff, Database, ShieldAlert, 
  Target, BarChart, Zap, TrendingUp, TrendingDown, Table as TableIcon, Filter, 
  ChevronLeft, ChevronRight, Maximize2, Minimize2, XCircle, Check,
  Grid3x3, Layers, ChevronUp, ChevronRight as ChevronRightIcon,
  Landmark, Building2, Store, CreditCard, FileText, AlertOctagon,
  TrendingUp as TrendingUpIcon, TrendingDown as TrendingDownIcon
} from 'lucide-react';
import * as XLSX from 'xlsx';
import {
  BarChart as RechartsBarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  PieChart as RechartsPieChart, Pie, Cell, ResponsiveContainer, LineChart as RechartsLineChart, Line,
  ScatterChart, Scatter, ZAxis, ComposedChart, Area, Radar, RadarChart, PolarGrid,
  PolarAngleAxis, PolarRadiusAxis, Treemap, SunburstChart
} from 'recharts';

const API_BASE = window.location.hostname === "localhost"
  ? "http://localhost/revenue2/backend/Treasury"
  : "https://revenuetreasury.goserveph.com/backend/Treasury";

// Statistical functions
const Stats = {
  mean: (arr) => {
    if (!arr || !arr.length) return 0;
    let sum = 0;
    for (let i = 0; i < arr.length; i++) sum += arr[i];
    return sum / arr.length;
  },
  median: (arr) => {
    if (!arr || !arr.length) return 0;
    const sorted = arr.slice().sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
  },
  mode: (arr) => {
    if (!arr || !arr.length) return 0;
    const frequency = {};
    let maxFreq = 0;
    let mode = arr[0];
    
    for (let i = 0; i < arr.length; i++) {
      const num = arr[i];
      frequency[num] = (frequency[num] || 0) + 1;
      if (frequency[num] > maxFreq) {
        maxFreq = frequency[num];
        mode = num;
      }
    }
    return mode;
  },
  standardDeviation: (arr) => {
    if (!arr || arr.length < 2) return 0;
    const m = Stats.mean(arr);
    let variance = 0;
    for (let i = 0; i < arr.length; i++) {
      variance += Math.pow(arr[i] - m, 2);
    }
    return Math.sqrt(variance / arr.length);
  },
  percentile: (arr, p) => {
    if (!arr || !arr.length) return 0;
    const sorted = arr.slice().sort((a, b) => a - b);
    const pos = (sorted.length - 1) * p;
    const base = Math.floor(pos);
    const rest = pos - base;
    return sorted[base] + rest * ((sorted[base + 1] || sorted[base]) - sorted[base]);
  },
  skewness: (arr) => {
    if (!arr || arr.length < 3) return 0;
    const mean = Stats.mean(arr);
    const stdDev = Stats.standardDeviation(arr);
    if (stdDev === 0) return 0;
    
    let sum = 0;
    for (let i = 0; i < arr.length; i++) {
      sum += Math.pow((arr[i] - mean) / stdDev, 3);
    }
    return sum / arr.length;
  },
  kurtosis: (arr) => {
    if (!arr || arr.length < 4) return 0;
    const mean = Stats.mean(arr);
    const stdDev = Stats.standardDeviation(arr);
    if (stdDev === 0) return 0;
    
    let sum = 0;
    for (let i = 0; i < arr.length; i++) {
      sum += Math.pow((arr[i] - mean) / stdDev, 4);
    }
    return (sum / arr.length) - 3;
  }
};

export default function Anomaly() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [anomalyData, setAnomalyData] = useState(null);
  const [alerts, setAlerts] = useState([]);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [availableYears, setAvailableYears] = useState([]);
  const [detectionMethod, setDetectionMethod] = useState('zscore');
  const [threshold, setThreshold] = useState(2.0);
  const [showDetails, setShowDetails] = useState({});
  const [systemFilter, setSystemFilter] = useState('all');
  const [dataSource, setDataSource] = useState('sample');
  const [viewMode, setViewMode] = useState('overview');
  const [monthlyData, setMonthlyData] = useState([]);
  const [systemData, setSystemData] = useState({
    rpt: [],
    business: [],
    market: []
  });
  const [yearDropdownOpen, setYearDropdownOpen] = useState(false);
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [totalCollection, setTotalCollection] = useState({
    total: 0,
    rpt: 0,
    business: 0,
    market: 0
  });
  const [alertFilter, setAlertFilter] = useState('all');
  const [expandedView, setExpandedView] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage, setItemsPerPage] = useState(10);
  const [detectionHistory, setDetectionHistory] = useState([]);

  // Fetch available years from database on component mount
  useEffect(() => {
    fetchAvailableYears();
  }, []);

  // Run detection when parameters change
  useEffect(() => {
    if (availableYears.length > 0) {
      detectAnomalies();
    }
  }, [selectedYear, detectionMethod, threshold, systemFilter]);

  // Save detection to history
  useEffect(() => {
    if (anomalyData && anomalyData.totalAnomalies > 0) {
      const historyEntry = {
        id: Date.now(),
        timestamp: new Date().toISOString(),
        year: selectedYear,
        system: systemFilter,
        method: detectionMethod,
        threshold: threshold,
        anomalies: anomalyData.totalAnomalies,
        severityBreakdown: anomalyData.bySeverity
      };
      setDetectionHistory(prev => [historyEntry, ...prev.slice(0, 9)]);
    }
  }, [anomalyData]);

  // Fetch available years from all databases
  const fetchAvailableYears = async () => {
    try {
      setLoading(true);
      const response = await fetch(`${API_BASE}/collection_api.php?action=get_available_years`);
      const data = await response.json();
      
      if (data.success && data.years && data.years.length > 0) {
        const sortedYears = data.years
          .filter(year => year >= 2000 && year <= 2100)
          .sort((a, b) => b - a);
        
        setAvailableYears(sortedYears);
        
        if (sortedYears.length > 0 && !sortedYears.includes(selectedYear)) {
          setSelectedYear(sortedYears[0]);
        }
      } else {
        const currentYear = new Date().getFullYear();
        setAvailableYears([currentYear, currentYear - 1]);
      }
    } catch (err) {
      console.error('Error fetching years:', err);
      const currentYear = new Date().getFullYear();
      setAvailableYears([currentYear, currentYear - 1]);
    } finally {
      setLoading(false);
    }
  };

  // Fetch revenue data for all systems
  const fetchRevenueData = async () => {
    try {
      const allData = {
        rpt: null,
        business: null,
        market: null,
        all: null
      };

      // Fetch data for each system
      const systems = ['rpt', 'business', 'market'];
      
      for (const system of systems) {
        try {
          const response = await fetch(
            `${API_BASE}/collection_api.php?action=get_collection_data&year=${selectedYear}&system=${system}&range=month`
          );
          
          if (response.ok) {
            const data = await response.json();
            if (data.success) {
              allData[system] = data;
            }
          }
        } catch (err) {
          console.log(`Error fetching ${system} data:`, err.message);
        }
      }

      // Also fetch combined data
      try {
        const response = await fetch(
          `${API_BASE}/collection_api.php?action=get_collection_data&year=${selectedYear}&system=all&range=month`
        );
        
        if (response.ok) {
          const data = await response.json();
          if (data.success) {
            allData.all = data;
          }
        }
      } catch (err) {
        console.log('Error fetching combined data:', err.message);
      }

      // Check if we got any real data
      const hasRealData = systems.some(system => allData[system] !== null) || allData.all !== null;
      
      if (hasRealData) {
        setDataSource('api');
        return allData;
      } else {
        throw new Error('No data received from API');
      }
    } catch (err) {
      console.log('Using sample data:', err.message);
      setDataSource('sample');
      return generateSampleData();
    }
  };

  const generateSampleData = () => {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    const generateSystemData = (systemName, baseAmount, anomalies) => {
      const data = [];
      for (let i = 0; i < 12; i++) {
        let amount = baseAmount + (Math.random() * baseAmount * 0.3);
        let transactionCount = Math.round(10 + Math.random() * 20);
        
        if (anomalies.includes(i)) {
          if (i === 2 || i === 10) {
            amount *= 2.5;
            transactionCount *= 1.8;
          }
          if (i === 7) {
            amount *= 0.3;
            transactionCount *= 0.4;
          }
        }
        
        data.push({
          month: i + 1,
          month_name: monthNames[i],
          collected_amount: Math.round(amount),
          target_amount: Math.round(baseAmount * 1.1),
          transaction_count: transactionCount,
          system: systemName,
          variance: Math.round((amount - (baseAmount * 1.1)) / (baseAmount * 1.1) * 100)
        });
      }
      return data;
    };

    const rptData = generateSystemData('RPT', 50000, [2, 7, 10]);
    const businessData = generateSystemData('Business Tax', 20000, [3, 8, 11]);
    const marketData = generateSystemData('Market Rent', 15000, [1, 6, 9]);

    return {
      rpt: {
        success: true,
        monthly: rptData,
        total: {
          collected_amount: rptData.reduce((sum, m) => sum + m.collected_amount, 0),
          target_amount: rptData.reduce((sum, m) => sum + m.target_amount, 0)
        }
      },
      business: {
        success: true,
        monthly: businessData,
        total: {
          collected_amount: businessData.reduce((sum, m) => sum + m.collected_amount, 0),
          target_amount: businessData.reduce((sum, m) => sum + m.target_amount, 0)
        }
      },
      market: {
        success: true,
        monthly: marketData,
        total: {
          collected_amount: marketData.reduce((sum, m) => sum + m.collected_amount, 0),
          target_amount: marketData.reduce((sum, m) => sum + m.target_amount, 0)
        }
      },
      all: {
        success: true,
        monthly: [...rptData, ...businessData, ...marketData],
        system_breakdown: [
          { system: 'RPT', collected_amount: rptData.reduce((sum, m) => sum + m.collected_amount, 0) },
          { system: 'Business Tax', collected_amount: businessData.reduce((sum, m) => sum + m.collected_amount, 0) },
          { system: 'Market Rent', collected_amount: marketData.reduce((sum, m) => sum + m.collected_amount, 0) }
        ],
        total: {
          collected_amount: rptData.reduce((sum, m) => sum + m.collected_amount, 0) +
                           businessData.reduce((sum, m) => sum + m.collected_amount, 0) +
                           marketData.reduce((sum, m) => sum + m.collected_amount, 0)
        }
      }
    };
  };

  const detectAnomalies = async () => {
    try {
      setIsAnalyzing(true);
      setError(null);
      
      const allData = await fetchRevenueData();
      
      // Set system data
      setSystemData({
        rpt: allData.rpt?.monthly || [],
        business: allData.business?.monthly || [],
        market: allData.market?.monthly || []
      });

      // Calculate total collections
      const total = {
        total: allData.all?.total?.collected_amount || 0,
        rpt: allData.rpt?.total?.collected_amount || 0,
        business: allData.business?.total?.collected_amount || 0,
        market: allData.market?.total?.collected_amount || 0
      };
      setTotalCollection(total);

      // Get data based on system filter
      let dataToAnalyze = [];
      if (systemFilter === 'all') {
        dataToAnalyze = allData.all?.monthly || [];
      } else if (systemFilter === 'rpt') {
        dataToAnalyze = allData.rpt?.monthly || [];
      } else if (systemFilter === 'business') {
        dataToAnalyze = allData.business?.monthly || [];
      } else if (systemFilter === 'market') {
        dataToAnalyze = allData.market?.monthly || [];
      }

      setMonthlyData(dataToAnalyze);
      
      // Detect anomalies in the data
      const anomalies = detectAnomaliesInData(dataToAnalyze);
      const generatedAlerts = generateAlerts(anomalies, systemFilter);
      const summary = prepareAnomalySummary(anomalies, dataToAnalyze, total);
      
      setAlerts(generatedAlerts);
      setAnomalyData(summary);
      
    } catch (err) {
      console.error('Error detecting anomalies:', err);
      setError(err.message);
    } finally {
      setIsAnalyzing(false);
    }
  };

  const detectAnomaliesInData = (data) => {
    const anomalies = [];
    const revenues = data.map(m => m.collected_amount) || [];
    
    if (revenues.length === 0) return anomalies;

    if (detectionMethod === 'zscore') {
      const mean = Stats.mean(revenues);
      const stdDev = Stats.standardDeviation(revenues);
      
      revenues.forEach((value, index) => {
        const zScore = Math.abs((value - mean) / stdDev);
        if (zScore > threshold) {
          anomalies.push({
            index,
            value,
            zScore: zScore.toFixed(2),
            method: 'zscore',
            severity: zScore > 3 ? 'high' : zScore > 2 ? 'medium' : 'low',
            month: data[index]?.month_name || `Month ${index + 1}`,
            system: data[index]?.system || systemFilter,
            deviation: ((value - mean) / mean * 100).toFixed(2)
          });
        }
      });
    } 
    else if (detectionMethod === 'iqr') {
      const q1 = Stats.percentile(revenues, 0.25);
      const q3 = Stats.percentile(revenues, 0.75);
      const iqr = q3 - q1;
      const lowerBound = q1 - (threshold * iqr);
      const upperBound = q3 + (threshold * iqr);
      
      revenues.forEach((value, index) => {
        if (value < lowerBound || value > upperBound) {
          const deviation = value < lowerBound ? 
            ((lowerBound - value) / iqr).toFixed(2) : 
            ((value - upperBound) / iqr).toFixed(2);
          
          anomalies.push({
            index,
            value,
            deviation,
            method: 'iqr',
            severity: Math.abs(deviation) > 2 ? 'high' : 'medium',
            month: data[index]?.month_name || `Month ${index + 1}`,
            system: data[index]?.system || systemFilter,
            isBelow: value < lowerBound
          });
        }
      });
    }
    
    return anomalies;
  };

  const generateAlerts = (anomalies, system) => {
    const alerts = [];

    anomalies.forEach(anomaly => {
      const systemName = system === 'all' ? 'All Systems' : 
                        system === 'rpt' ? 'RPT' : 
                        system === 'business' ? 'Business Tax' : 'Market Rent';
      
      let description = '';
      if (anomaly.method === 'zscore') {
        description = `${systemName}: Z-score of ${anomaly.zScore} detected for ${anomaly.month}`;
      } else if (anomaly.method === 'iqr') {
        description = `${systemName}: IQR deviation of ${anomaly.deviation} detected for ${anomaly.month}`;
      }

      alerts.push({
        id: `${system}-${anomaly.index}-${Date.now()}`,
        title: `Anomaly Detected in ${systemName}`,
        description,
        month: anomaly.month,
        system: systemName,
        severity: anomaly.severity,
        value: anomaly.value,
        method: anomaly.method,
        timestamp: new Date().toISOString(),
        isResolved: false,
        deviation: anomaly.deviation,
        isBelow: anomaly.isBelow
      });
    });

    return alerts.sort((a, b) => {
      const severityOrder = { high: 3, medium: 2, low: 1 };
      return severityOrder[b.severity] - severityOrder[a.severity];
    });
  };

  const prepareAnomalySummary = (anomalies, data, totals) => {
    const summary = {
      totalAnomalies: anomalies.length,
      bySeverity: { high: 0, medium: 0, low: 0 },
      byMethod: { zscore: 0, iqr: 0 },
      detectionMetrics: {},
      monthlyStats: data || [],
      systemTotals: totals,
      statisticalSummary: {}
    };

    anomalies.forEach(anomaly => {
      summary.bySeverity[anomaly.severity]++;
      summary.byMethod[anomaly.method]++;
    });

    const totalDataPoints = data.length || 12;
    summary.detectionMetrics = {
      anomalyRate: totalDataPoints > 0 ? (anomalies.length / totalDataPoints * 100).toFixed(2) : 0,
      confidence: Math.min(95, 100 - (anomalies.length / 10)),
      precision: Math.min(98, 100 - (summary.bySeverity.high * 2)),
      recall: Math.min(95, 90 + (summary.bySeverity.medium * 1)),
      f1Score: totalDataPoints > 0 ? 
        (2 * (summary.detectionMetrics.precision * summary.detectionMetrics.recall) / 
        (summary.detectionMetrics.precision + summary.detectionMetrics.recall)).toFixed(2) : 0
    };

    // Statistical summary
    const revenues = data.map(m => m.collected_amount);
    if (revenues.length > 0) {
      summary.statisticalSummary = {
        mean: Stats.mean(revenues),
        median: Stats.median(revenues),
        mode: Stats.mode(revenues),
        stdDev: Stats.standardDeviation(revenues),
        skewness: Stats.skewness(revenues),
        kurtosis: Stats.kurtosis(revenues),
        min: Math.min(...revenues),
        max: Math.max(...revenues),
        range: Math.max(...revenues) - Math.min(...revenues),
        q1: Stats.percentile(revenues, 0.25),
        q3: Stats.percentile(revenues, 0.75),
        iqr: Stats.percentile(revenues, 0.75) - Stats.percentile(revenues, 0.25)
      };
    }

    return summary;
  };

  const formatCurrency = (amount) => {
    if (amount === null || amount === undefined || isNaN(amount)) {
      return '₱0';
    }
    
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    
    if (numAmount >= 1000000000) {
      return `₱${(numAmount / 1000000000).toFixed(2)}B`;
    }
    if (numAmount >= 1000000) {
      return `₱${(numAmount / 1000000000).toFixed(2)}M`;
    }
    if (numAmount >= 1000) {
      return `₱${(numAmount / 1000).toFixed(2)}K`;
    }
    return `₱${numAmount.toFixed(2)}`;
  };

  const resolveAlert = (alertId) => {
    setAlerts(prev => prev.map(alert => 
      alert.id === alertId ? { ...alert, isResolved: true } : alert
    ));
  };

  const resolveAllAlerts = () => {
    setAlerts(prev => prev.map(alert => ({ ...alert, isResolved: true })));
  };

  const exportData = () => {
    if (!anomalyData) return;

    const wb = XLSX.utils.book_new();
    const dateStr = new Date().toISOString().split('T')[0];
    
    // Summary sheet
    const summaryData = [{
      'Year': selectedYear,
      'System': systemFilter === 'all' ? 'All Systems' : 
                systemFilter === 'rpt' ? 'RPT' : 
                systemFilter === 'business' ? 'Business Tax' : 'Market Rent',
      'Detection Method': detectionMethod.toUpperCase(),
      'Threshold': threshold,
      'Total Anomalies': anomalyData.totalAnomalies,
      'High Severity': anomalyData.bySeverity.high,
      'Medium Severity': anomalyData.bySeverity.medium,
      'Low Severity': anomalyData.bySeverity.low,
      'Anomaly Rate': `${anomalyData.detectionMetrics?.anomalyRate || 0}%`,
      'Detection Confidence': `${anomalyData.detectionMetrics?.confidence || 0}%`,
      'Total Collection': formatCurrency(anomalyData.systemTotals?.total || 0),
      'Data Source': dataSource === 'api' ? 'Database' : 'Sample',
      'Export Date': new Date().toLocaleString()
    }];

    const ws1 = XLSX.utils.json_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, ws1, 'Summary');

    // Alerts sheet
    if (alerts.length > 0) {
      const alertData = alerts.map(alert => ({
        'ID': alert.id,
        'Title': alert.title,
        'Description': alert.description,
        'System': alert.system,
        'Month': alert.month,
        'Severity': alert.severity.toUpperCase(),
        'Value': formatCurrency(alert.value),
        'Detection Method': alert.method.toUpperCase(),
        'Deviation': alert.deviation ? `${alert.deviation}%` : 'N/A',
        'Timestamp': new Date(alert.timestamp).toLocaleString(),
        'Status': alert.isResolved ? 'Resolved' : 'Active',
        'Direction': alert.isBelow ? 'Below Normal' : 'Above Normal'
      }));

      const ws2 = XLSX.utils.json_to_sheet(alertData);
      XLSX.utils.book_append_sheet(wb, ws2, 'Alerts');
    }

    // Monthly data sheet
    if (monthlyData.length > 0) {
      const monthlyDataSheet = monthlyData.map(month => ({
        'Month': month.month_name,
        'System': month.system || (systemFilter === 'all' ? 'All' : systemFilter.toUpperCase()),
        'Collected Amount': month.collected_amount,
        'Target Amount': month.target_amount,
        'Collection Rate': month.target_amount > 0 ? 
          `${((month.collected_amount / month.target_amount) * 100).toFixed(2)}%` : '0%',
        'Transactions': month.transaction_count,
        'Variance': month.variance || 0,
        'Is Anomaly': alerts.some(a => a.month === month.month_name && !a.isResolved) ? 'Yes' : 'No',
        'Anomaly Severity': alerts.find(a => a.month === month.month_name && !a.isResolved)?.severity || 'None'
      }));

      const ws3 = XLSX.utils.json_to_sheet(monthlyDataSheet);
      XLSX.utils.book_append_sheet(wb, ws3, 'Monthly Data');
    }

    // Statistical Summary sheet
    const statsData = [{
      'Mean': formatCurrency(anomalyData.statisticalSummary?.mean || 0),
      'Median': formatCurrency(anomalyData.statisticalSummary?.median || 0),
      'Mode': formatCurrency(anomalyData.statisticalSummary?.mode || 0),
      'Standard Deviation': formatCurrency(anomalyData.statisticalSummary?.stdDev || 0),
      'Minimum': formatCurrency(anomalyData.statisticalSummary?.min || 0),
      'Maximum': formatCurrency(anomalyData.statisticalSummary?.max || 0),
      'Range': formatCurrency(anomalyData.statisticalSummary?.range || 0),
      'Q1 (25th Percentile)': formatCurrency(anomalyData.statisticalSummary?.q1 || 0),
      'Q3 (75th Percentile)': formatCurrency(anomalyData.statisticalSummary?.q3 || 0),
      'IQR': formatCurrency(anomalyData.statisticalSummary?.iqr || 0),
      'Skewness': anomalyData.statisticalSummary?.skewness?.toFixed(4) || 0,
      'Kurtosis': anomalyData.statisticalSummary?.kurtosis?.toFixed(4) || 0
    }];

    const ws4 = XLSX.utils.json_to_sheet(statsData);
    XLSX.utils.book_append_sheet(wb, ws4, 'Statistical Summary');

    XLSX.writeFile(wb, `Anomaly_Report_${selectedYear}_${dateStr}.xlsx`);
  };

  const exportPDF = () => {
    // This would typically be implemented with a PDF library like jsPDF
    alert('PDF export functionality would be implemented with jsPDF library');
  };

  const toggleDetails = (alertId) => {
    setShowDetails(prev => ({
      ...prev,
      [alertId]: !prev[alertId]
    }));
  };

  // Prepare chart data
  const prepareChartData = () => {
    if (!monthlyData.length) return [];
    
    return monthlyData.map((month, index) => {
      const isAnomaly = alerts.some(alert => 
        alert.month === month.month_name && !alert.isResolved
      );
      const alert = alerts.find(a => a.month === month.month_name && !a.isResolved);
      
      return {
        name: month.month_name,
        collected: month.collected_amount,
        target: month.target_amount,
        transactions: month.transaction_count,
        rate: month.target_amount > 0 ? (month.collected_amount / month.target_amount) * 100 : 0,
        isAnomaly: isAnomaly,
        anomalySeverity: alert?.severity || null,
        anomalyValue: alert?.value || null,
        system: month.system || (systemFilter === 'all' ? 'All Systems' : 
                systemFilter === 'rpt' ? 'RPT' : 
                systemFilter === 'business' ? 'Business Tax' : 'Market Rent'),
        variance: month.variance || 0,
        deviation: alert?.deviation || 0
      };
    });
  };

  const prepareSystemComparisonData = () => {
    return [
      { name: 'RPT', value: totalCollection.rpt, color: '#4F46E5' },
      { name: 'Business Tax', value: totalCollection.business, color: '#10B981' },
      { name: 'Market Rent', value: totalCollection.market, color: '#F59E0B' }
    ];
  };

  const prepareSeverityData = () => {
    if (!anomalyData) return [];
    return [
      { name: 'High', value: anomalyData.bySeverity.high, color: '#EF4444' },
      { name: 'Medium', value: anomalyData.bySeverity.medium, color: '#F59E0B' },
      { name: 'Low', value: anomalyData.bySeverity.low, color: '#3B82F6' }
    ];
  };

  const getSystemColor = (system) => {
    switch(system) {
      case 'RPT': return '#4F46E5';
      case 'Business Tax': return '#10B981';
      case 'Market Rent': return '#F59E0B';
      case 'All Systems': return '#6B7280';
      default: return '#6B7280';
    }
  };

  const getSeverityColor = (severity) => {
    switch(severity) {
      case 'high': return '#EF4444';
      case 'medium': return '#F59E0B';
      case 'low': return '#3B82F6';
      default: return '#6B7280';
    }
  };

  const getSystemIcon = (system) => {
    switch(system) {
      case 'rpt': return Landmark;
      case 'business': return Building2;
      case 'market': return Store;
      case 'all': return CreditCard;
      default: return CreditCard;
    }
  };

  // Filter alerts based on selected filter
  const filteredAlerts = alerts.filter(alert => {
    if (alertFilter === 'all') return true;
    if (alertFilter === 'active') return !alert.isResolved;
    if (alertFilter === 'resolved') return alert.isResolved;
    if (alertFilter === 'high') return alert.severity === 'high' && !alert.isResolved;
    if (alertFilter === 'medium') return alert.severity === 'medium' && !alert.isResolved;
    if (alertFilter === 'low') return alert.severity === 'low' && !alert.isResolved;
    return true;
  });

  // Pagination logic
  const totalPages = Math.ceil(filteredAlerts.length / itemsPerPage);
  const paginatedAlerts = filteredAlerts.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  if (loading && !anomalyData) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-6">
        <div className="flex flex-col items-center justify-center h-[70vh]">
          <div className="relative">
            <div className="animate-spin rounded-full h-20 w-20 border-t-2 border-b-2 border-blue-600 mb-4"></div>
            <div className="absolute inset-0 flex items-center justify-center">
              <ShieldAlert className="w-10 h-10 text-blue-600" />
            </div>
          </div>
          <p className="text-gray-700 text-lg font-medium mt-4">Loading Anomaly Detection System</p>
          <p className="text-sm text-gray-500 mt-2">Fetching available years and analyzing data...</p>
        </div>
      </div>
    );
  }

  const chartData = prepareChartData();
  const systemComparisonData = prepareSystemComparisonData();
  const severityData = prepareSeverityData();
  const anomalyCount = anomalyData?.totalAnomalies || 0;
  const selectedSystemName = systemFilter === 'all' ? 'All Systems' : 
                            systemFilter === 'rpt' ? 'RPT' : 
                            systemFilter === 'business' ? 'Business Tax' : 'Market Rent';

  return (
    <div className={`min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 ${expandedView ? 'fixed inset-0 z-50 overflow-auto' : ''}`}>
      {/* Header */}
      <div className="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div className="flex-1">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg">
                  <ShieldAlert className="w-7 h-7 text-white" />
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    Anomaly Detection Dashboard
                    {anomalyCount > 0 && (
                      <span className="text-sm px-3 py-1 bg-red-100 text-red-800 rounded-full font-medium">
                        {anomalyCount} {anomalyCount === 1 ? 'Anomaly' : 'Anomalies'} Detected
                      </span>
                    )}
                  </h1>
                  <p className="text-gray-600 mt-1 flex items-center gap-2">
                    <Database className="w-4 h-4" />
                    Detect unusual patterns in revenue collection • Real-time monitoring
                  </p>
                </div>
              </div>
            </div>
            
            <div className="flex flex-wrap gap-3">
              {/* Expand/Collapse View */}
              <button
                onClick={() => setExpandedView(!expandedView)}
                className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 flex items-center gap-2"
              >
                {expandedView ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
                <span>{expandedView ? 'Collapse' : 'Expand'}</span>
              </button>
              
              {/* Export Options */}
              <div className="relative group">
                <button className="px-4 py-2 bg-gradient-to-r from-gray-900 to-black text-white rounded-lg hover:opacity-90 flex items-center gap-2">
                  <Download className="w-4 h-4" />
                  <span>Export</span>
                  <ChevronDown className="w-4 h-4" />
                </button>
                <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                  <button
                    onClick={exportData}
                    className="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-2"
                  >
                    <FileText className="w-4 h-4" />
                    <div>
                      <div className="font-medium">Export to Excel</div>
                      <div className="text-xs text-gray-500">.xlsx format</div>
                    </div>
                  </button>
                  <button
                    onClick={exportPDF}
                    className="w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors flex items-center gap-2"
                  >
                    <FileText className="w-4 h-4" />
                    <div>
                      <div className="font-medium">Export to PDF</div>
                      <div className="text-xs text-gray-500">Report format</div>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          {/* Control Panel */}
          <div className="mt-6">
            <div className="flex flex-col lg:flex-row gap-4">
              {/* Left Panel - System Selection */}
              <div className="flex-1">
                <div className="flex flex-wrap gap-2">
                  {[
                    { id: 'all', label: 'All Systems', icon: CreditCard, color: 'bg-gray-900' },
                    { id: 'rpt', label: 'Real Property Tax', icon: Landmark, color: 'bg-blue-600' },
                    { id: 'business', label: 'Business Tax', icon: Building2, color: 'bg-green-600' },
                    { id: 'market', label: 'Market Rent', icon: Store, color: 'bg-yellow-600' }
                  ].map((system) => {
                    const Icon = system.icon;
                    const isActive = systemFilter === system.id;
                    return (
                      <button
                        key={system.id}
                        onClick={() => setSystemFilter(system.id)}
                        className={`px-4 py-3 rounded-lg flex items-center gap-3 transition-all ${isActive ? 'ring-2 ring-offset-2 ring-gray-900' : ''}`}
                        style={{
                          background: isActive ? 
                            `linear-gradient(135deg, ${system.color.replace('bg-', '') === 'gray-900' ? '#111827' : 
                              system.color.replace('bg-', '') === 'blue-600' ? '#2563EB' :
                              system.color.replace('bg-', '') === 'green-600' ? '#059669' : '#D97706'}, #1F2937)` :
                            'white',
                          color: isActive ? 'white' : '#374151',
                          border: isActive ? 'none' : '1px solid #D1D5DB'
                        }}
                      >
                        <div className={`p-2 rounded-lg ${isActive ? 'bg-white/20' : 'bg-gray-100'}`}>
                          <Icon className={`w-5 h-5 ${isActive ? 'text-white' : 'text-gray-600'}`} />
                        </div>
                        <div className="text-left">
                          <div className="font-medium">{system.label}</div>
                          <div className="text-sm opacity-80">
                            {formatCurrency(totalCollection[system.id === 'all' ? 'total' : system.id])}
                          </div>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>
              
              {/* Right Panel - Controls */}
              <div className="flex flex-col sm:flex-row gap-4">
                {/* View Mode Toggle */}
                <div className="inline-flex rounded-lg border border-gray-300 p-1 bg-white">
                  <button
                    onClick={() => setViewMode('overview')}
                    className={`px-3 py-2 text-sm rounded-md transition-all flex items-center gap-2 ${
                      viewMode === 'overview' 
                        ? 'bg-gradient-to-r from-gray-900 to-black text-white shadow-sm' 
                        : 'text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    <Grid3x3 className="w-4 h-4" />
                    Overview
                  </button>
                  <button
                    onClick={() => setViewMode('charts')}
                    className={`px-3 py-2 text-sm rounded-md transition-all flex items-center gap-2 ${
                      viewMode === 'charts' 
                        ? 'bg-gradient-to-r from-gray-900 to-black text-white shadow-sm' 
                        : 'text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    <BarChart3 className="w-4 h-4" />
                    Charts
                  </button>
                  <button
                    onClick={() => setViewMode('table')}
                    className={`px-3 py-2 text-sm rounded-md transition-all flex items-center gap-2 ${
                      viewMode === 'table' 
                        ? 'bg-gradient-to-r from-gray-900 to-black text-white shadow-sm' 
                        : 'text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    <TableIcon className="w-4 h-4" />
                    Table
                  </button>
                </div>
                
                {/* Year Selection */}
                <div className="relative">
                  <button
                    onClick={() => setYearDropdownOpen(!yearDropdownOpen)}
                    className="flex items-center gap-3 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700 bg-white w-full sm:w-auto"
                  >
                    <Calendar className="w-5 h-5" />
                    <div className="text-left">
                      <div className="text-sm text-gray-500">Year</div>
                      <div className="font-medium">{selectedYear}</div>
                    </div>
                    <ChevronDown className="w-4 h-4" />
                  </button>
                  
                  {yearDropdownOpen && (
                    <>
                      <div 
                        className="fixed inset-0 z-40" 
                        onClick={() => setYearDropdownOpen(false)}
                      ></div>
                      <div className="absolute top-full right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                        <div className="py-2">
                          <div className="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Available Years
                          </div>
                          <div className="max-h-60 overflow-y-auto">
                            {availableYears.map(year => (
                              <button
                                key={year}
                                onClick={() => {
                                  setSelectedYear(year);
                                  setYearDropdownOpen(false);
                                }}
                                className={`w-full text-left px-4 py-2 hover:bg-gray-50 transition-colors flex items-center justify-between ${
                                  selectedYear === year 
                                    ? 'bg-gray-100 text-gray-900 font-medium' 
                                    : 'text-gray-700'
                                }`}
                              >
                                <div className="flex items-center gap-2">
                                  <Calendar className="w-4 h-4" />
                                  <span>{year}</span>
                                </div>
                                {selectedYear === year && (
                                  <Check className="w-4 h-4 text-gray-600" />
                                )}
                              </button>
                            ))}
                          </div>
                        </div>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </div>
            
            {/* Detection Settings */}
            <div className="mt-4 flex flex-wrap items-center gap-4">
              <div className="flex items-center gap-3">
                <span className="text-sm font-medium text-gray-700">Detection Method:</span>
                <div className="flex gap-2">
                  {[
                    { id: 'zscore', label: 'Z-Score', icon: BarChart3, description: 'Standard deviations from mean' },
                    { id: 'iqr', label: 'IQR', icon: LineChart, description: 'Interquartile range method' }
                  ].map((method) => {
                    const Icon = method.icon;
                    return (
                      <button
                        key={method.id}
                        onClick={() => setDetectionMethod(method.id)}
                        className={`px-4 py-2 rounded-lg flex items-center gap-2 transition-all relative group ${
                          detectionMethod === method.id
                            ? 'bg-gradient-to-r from-gray-900 to-black text-white'
                            : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
                        }`}
                      >
                        <Icon className="w-4 h-4" />
                        <span className="text-sm">{method.label}</span>
                        <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-1 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                          {method.description}
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>
              
              {/* Threshold Control */}
              <div className="flex items-center gap-3 bg-white border border-gray-300 rounded-lg px-4 py-2">
                <Target className="w-5 h-5 text-gray-500" />
                <div className="text-left">
                  <div className="text-sm text-gray-500">Threshold</div>
                  <div className="font-medium">{threshold.toFixed(1)}</div>
                </div>
                <input
                  type="range"
                  min="1"
                  max="5"
                  step="0.5"
                  value={threshold}
                  onChange={(e) => setThreshold(parseFloat(e.target.value))}
                  className="w-32 accent-gray-900"
                />
                <div className="text-xs text-gray-500">
                  {threshold <= 2 ? 'Standard' : threshold <= 3 ? 'Strict' : 'Aggressive'}
                </div>
              </div>
              
              {/* Refresh Button */}
              <button
                onClick={detectAnomalies}
                disabled={isAnalyzing}
                className="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:opacity-90 disabled:opacity-50 flex items-center gap-2"
              >
                {isAnalyzing ? (
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                ) : (
                  <RefreshCw className="w-4 h-4" />
                )}
                <span>{isAnalyzing ? 'Analyzing...' : 'Re-analyze'}</span>
              </button>
            </div>
            
            {/* Data Source Info */}
            <div className="mt-4 flex flex-wrap items-center gap-4 text-sm">
              <div className="flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-full">
                <Database className="w-4 h-4 text-gray-600" />
                <span className="text-gray-700">Data Source:</span>
                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                  dataSource === 'api' 
                    ? 'bg-green-100 text-green-800' 
                    : 'bg-yellow-100 text-yellow-800'
                }`}>
                  {dataSource === 'api' ? 'Live Database' : 'Sample Data'}
                </span>
              </div>
              
              <div className="flex items-center gap-2">
                <DollarSign className="w-4 h-4 text-gray-600" />
                <span className="text-gray-700">Total Collection:</span>
                <span className="font-bold text-gray-900">
                  {formatCurrency(anomalyData?.systemTotals?.total || 0)}
                </span>
              </div>
              
              {anomalyData && (
                <div className="flex items-center gap-2">
                  <AlertOctagon className="w-4 h-4 text-red-600" />
                  <span className="text-gray-700">Anomalies:</span>
                  <span className="font-bold text-gray-900">{anomalyCount}</span>
                  <span className="text-xs text-gray-500">
                    ({anomalyData.detectionMetrics?.anomalyRate || 0}% of data)
                  </span>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {error && (
          <div className="mb-6 bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-xl p-4">
            <div className="flex items-center gap-3">
              <AlertCircle className="w-6 h-6 text-red-600" />
              <div className="flex-1">
                <p className="text-red-700 font-medium">Error fetching data</p>
                <p className="text-red-600 text-sm mt-1">{error}</p>
                <p className="text-red-500 text-sm mt-2">Using sample data for demonstration purposes</p>
              </div>
            </div>
          </div>
        )}

        {/* View Mode Content */}
        {viewMode === 'overview' && (
          <div className="space-y-8">
            {/* Top Statistics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
              {/* Total Anomalies Card */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Total Anomalies</p>
                    <p className="text-3xl font-bold text-gray-900">{anomalyCount}</p>
                    <p className="text-sm text-gray-500 mt-1">
                      {anomalyData?.detectionMetrics?.anomalyRate || 0}% of data
                    </p>
                  </div>
                  <div className="p-3 bg-red-100 rounded-lg">
                    <AlertOctagon className="w-8 h-8 text-red-600" />
                  </div>
                </div>
                <div className="mt-4 flex gap-2">
                  <div className="flex-1">
                    <div className="flex items-center justify-between text-xs mb-1">
                      <span className="text-red-600">High</span>
                      <span>{anomalyData?.bySeverity.high || 0}</span>
                    </div>
                    <div className="h-1 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-red-500 rounded-full" 
                        style={{ width: `${(anomalyData?.bySeverity.high / anomalyCount) * 100 || 0}%` }}
                      ></div>
                    </div>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center justify-between text-xs mb-1">
                      <span className="text-yellow-600">Medium</span>
                      <span>{anomalyData?.bySeverity.medium || 0}</span>
                    </div>
                    <div className="h-1 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-yellow-500 rounded-full" 
                        style={{ width: `${(anomalyData?.bySeverity.medium / anomalyCount) * 100 || 0}%` }}
                      ></div>
                    </div>
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center justify-between text-xs mb-1">
                      <span className="text-blue-600">Low</span>
                      <span>{anomalyData?.bySeverity.low || 0}</span>
                    </div>
                    <div className="h-1 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-blue-500 rounded-full" 
                        style={{ width: `${(anomalyData?.bySeverity.low / anomalyCount) * 100 || 0}%` }}
                      ></div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Detection Confidence Card */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Detection Confidence</p>
                    <p className="text-3xl font-bold text-gray-900">
                      {anomalyData?.detectionMetrics?.confidence || 0}%
                    </p>
                    <p className="text-sm text-gray-500 mt-1">
                      {anomalyData?.detectionMetrics?.confidence >= 90 ? 'High confidence' : 
                       anomalyData?.detectionMetrics?.confidence >= 70 ? 'Moderate confidence' : 'Low confidence'}
                    </p>
                  </div>
                  <div className="p-3 bg-green-100 rounded-lg">
                    <ShieldAlert className="w-8 h-8 text-green-600" />
                  </div>
                </div>
                <div className="mt-4">
                  <div className="flex items-center justify-between text-xs mb-1">
                    <span>Precision: {anomalyData?.detectionMetrics?.precision || 0}%</span>
                    <span>Recall: {anomalyData?.detectionMetrics?.recall || 0}%</span>
                  </div>
                  <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full bg-gradient-to-r from-green-500 to-green-600 rounded-full" 
                      style={{ width: `${anomalyData?.detectionMetrics?.confidence || 0}%` }}
                    ></div>
                  </div>
                </div>
              </div>

              {/* Total Collection Card */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Total Collection</p>
                    <p className="text-3xl font-bold text-gray-900">
                      {formatCurrency(anomalyData?.systemTotals?.total || 0)}
                    </p>
                    <p className="text-sm text-gray-500 mt-1">
                      {selectedSystemName} • {selectedYear}
                    </p>
                  </div>
                  <div className="p-3 bg-blue-100 rounded-lg">
                    <DollarSign className="w-8 h-8 text-blue-600" />
                  </div>
                </div>
                <div className="mt-4 space-y-2">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-600">RPT</span>
                    <span className="font-medium">{formatCurrency(anomalyData?.systemTotals?.rpt || 0)}</span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-600">Business Tax</span>
                    <span className="font-medium">{formatCurrency(anomalyData?.systemTotals?.business || 0)}</span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-gray-600">Market Rent</span>
                    <span className="font-medium">{formatCurrency(anomalyData?.systemTotals?.market || 0)}</span>
                  </div>
                </div>
              </div>

              {/* Statistical Summary Card */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-gray-500 mb-1">Statistical Summary</p>
                    <p className="text-2xl font-bold text-gray-900">
                      {anomalyData?.statisticalSummary?.stdDev ? 
                        `σ: ${formatCurrency(anomalyData.statisticalSummary.stdDev)}` : 'N/A'}
                    </p>
                    <p className="text-sm text-gray-500 mt-1">
                      Mean: {formatCurrency(anomalyData?.statisticalSummary?.mean || 0)}
                    </p>
                  </div>
                  <div className="p-3 bg-purple-100 rounded-lg">
                    <BarChart className="w-8 h-8 text-purple-600" />
                  </div>
                </div>
                <div className="mt-4 grid grid-cols-2 gap-2 text-xs">
                  <div className="space-y-1">
                    <div className="text-gray-500">Median</div>
                    <div className="font-medium">{formatCurrency(anomalyData?.statisticalSummary?.median || 0)}</div>
                  </div>
                  <div className="space-y-1">
                    <div className="text-gray-500">Range</div>
                    <div className="font-medium">{formatCurrency(anomalyData?.statisticalSummary?.range || 0)}</div>
                  </div>
                  <div className="space-y-1">
                    <div className="text-gray-500">Skewness</div>
                    <div className="font-medium">{anomalyData?.statisticalSummary?.skewness?.toFixed(3) || 0}</div>
                  </div>
                  <div className="space-y-1">
                    <div className="text-gray-500">Kurtosis</div>
                    <div className="font-medium">{anomalyData?.statisticalSummary?.kurtosis?.toFixed(3) || 0}</div>
                  </div>
                </div>
              </div>
            </div>

            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Revenue Trend with Anomalies */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between mb-6">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">Revenue Trend with Anomalies</h3>
                    <p className="text-sm text-gray-500">Monthly collection with anomaly detection</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="flex items-center gap-1">
                      <div className="w-3 h-3 rounded-full bg-blue-500"></div>
                      <span className="text-xs text-gray-600">Collected</span>
                    </div>
                    <div className="flex items-center gap-1">
                      <div className="w-3 h-3 rounded-full bg-gray-300"></div>
                      <span className="text-xs text-gray-600">Target</span>
                    </div>
                  </div>
                </div>
                <div className="h-80">
                  <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart data={chartData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="name" />
                      <YAxis 
                        tickFormatter={(value) => formatCurrency(value).replace('₱', '')}
                      />
                      <Tooltip 
                        formatter={(value, name) => [
                          name === 'collected' || name === 'target' ? formatCurrency(value) : value,
                          name === 'collected' ? 'Collected Amount' : 
                          name === 'target' ? 'Target Amount' : name
                        ]}
                      />
                      <Legend />
                      <Area 
                        type="monotone" 
                        dataKey="target" 
                        fill="#f3f4f6" 
                        stroke="#9ca3af" 
                        strokeWidth={1}
                        name="Target Amount"
                      />
                      <Line 
                        type="monotone" 
                        dataKey="collected" 
                        stroke="#3b82f6" 
                        strokeWidth={3}
                        dot={(props) => {
                          const { cx, cy, payload } = props;
                          const isAnomaly = payload.isAnomaly;
                          const severity = payload.anomalySeverity;
                          
                          if (isAnomaly) {
                            let fill, stroke;
                            switch(severity) {
                              case 'high': fill = '#ef4444'; stroke = '#dc2626'; break;
                              case 'medium': fill = '#f59e0b'; stroke = '#d97706'; break;
                              case 'low': fill = '#3b82f6'; stroke = '#2563eb'; break;
                              default: fill = '#ef4444'; stroke = '#dc2626';
                            }
                            return (
                              <circle 
                                cx={cx} 
                                cy={cy} 
                                r={6} 
                                fill={fill}
                                stroke={stroke}
                                strokeWidth={2}
                              />
                            );
                          }
                          return <circle cx={cx} cy={cy} r={4} fill="#3b82f6" />;
                        }}
                        name="Collected Amount"
                      />
                    </ComposedChart>
                  </ResponsiveContainer>
                </div>
              </div>

              {/* System Comparison Pie Chart */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between mb-6">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">System Contribution</h3>
                    <p className="text-sm text-gray-500">Revenue distribution across systems</p>
                  </div>
                </div>
                <div className="h-80">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Pie
                        data={systemComparisonData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={({
                          cx,
                          cy,
                          midAngle,
                          innerRadius,
                          outerRadius,
                          percent,
                          index
                        }) => {
                          const RADIAN = Math.PI / 180;
                          const radius = innerRadius + (outerRadius - innerRadius) * 0.5;
                          const x = cx + radius * Math.cos(-midAngle * RADIAN);
                          const y = cy + radius * Math.sin(-midAngle * RADIAN);

                          return (
                            <text
                              x={x}
                              y={y}
                              fill="white"
                              textAnchor={x > cx ? 'start' : 'end'}
                              dominantBaseline="central"
                              className="text-sm font-semibold"
                            >
                              {`${(percent * 100).toFixed(0)}%`}
                            </text>
                          );
                        }}
                        outerRadius={80}
                        fill="#8884d8"
                        dataKey="value"
                      >
                        {systemComparisonData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Pie>
                      <Tooltip 
                        formatter={(value) => formatCurrency(value)}
                      />
                      <Legend />
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                <div className="mt-4 grid grid-cols-3 gap-2">
                  {systemComparisonData.map((system, index) => (
                    <div key={index} className="text-center">
                      <div className="flex items-center justify-center gap-1 mb-1">
                        <div 
                          className="w-3 h-3 rounded-full" 
                          style={{ backgroundColor: system.color }}
                        ></div>
                        <span className="text-xs font-medium text-gray-700">{system.name}</span>
                      </div>
                      <div className="text-sm font-bold text-gray-900">
                        {formatCurrency(system.value)}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Anomaly Severity Distribution */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Severity Distribution */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Anomaly Severity Distribution</h3>
                <div className="h-64">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={severityData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="name" />
                      <YAxis />
                      <Tooltip />
                      <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                        {severityData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>

              {/* Detection Method Comparison */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Detection Method Performance</h3>
                <div className="space-y-4">
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-gray-700">Current Method: {detectionMethod.toUpperCase()}</span>
                      <span className="text-sm font-bold text-gray-900">
                        {anomalyData?.detectionMetrics?.confidence || 0}%
                      </span>
                    </div>
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-blue-500 rounded-full" 
                        style={{ width: `${anomalyData?.detectionMetrics?.confidence || 0}%` }}
                      ></div>
                    </div>
                  </div>
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-gray-700">Precision</span>
                      <span className="text-sm font-bold text-gray-900">
                        {anomalyData?.detectionMetrics?.precision || 0}%
                      </span>
                    </div>
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-green-500 rounded-full" 
                        style={{ width: `${anomalyData?.detectionMetrics?.precision || 0}%` }}
                      ></div>
                    </div>
                  </div>
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-gray-700">Recall</span>
                      <span className="text-sm font-bold text-gray-900">
                        {anomalyData?.detectionMetrics?.recall || 0}%
                      </span>
                    </div>
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-yellow-500 rounded-full" 
                        style={{ width: `${anomalyData?.detectionMetrics?.recall || 0}%` }}
                      ></div>
                    </div>
                  </div>
                  <div className="pt-4 border-t border-gray-200">
                    <div className="text-sm text-gray-600">
                      <div className="flex items-center gap-2">
                        <Zap className="w-4 h-4 text-yellow-500" />
                        <span>Threshold: {threshold.toFixed(1)}</span>
                      </div>
                      <div className="flex items-center gap-2 mt-2">
                        <Target className="w-4 h-4 text-blue-500" />
                        <span>{threshold <= 2 ? 'Standard sensitivity' : threshold <= 3 ? 'High sensitivity' : 'Very high sensitivity'}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Recent Detection History */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-lg font-semibold text-gray-900">Recent Detection History</h3>
                  <span className="text-xs text-gray-500">Last 10 runs</span>
                </div>
                <div className="space-y-3 max-h-60 overflow-y-auto">
                  {detectionHistory.length > 0 ? (
                    detectionHistory.map((history, index) => (
                      <div key={history.id} className="p-3 bg-gray-50 rounded-lg">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <Clock className="w-4 h-4 text-gray-400" />
                            <span className="text-sm text-gray-600">
                              {new Date(history.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                            </span>
                          </div>
                          <span className="text-sm font-medium px-2 py-1 bg-gray-200 rounded">
                            {history.anomalies} anomalies
                          </span>
                        </div>
                        <div className="mt-2 text-xs text-gray-500">
                          {history.system} • {history.method} (T: {history.threshold})
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <Activity className="w-8 h-8 mx-auto mb-2 opacity-50" />
                      <p>No detection history yet</p>
                      <p className="text-sm">Run analysis to see history</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Charts View */}
        {viewMode === 'charts' && (
          <div className="space-y-8">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {/* Detailed Revenue Analysis */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-gray-900 mb-6">Detailed Revenue Analysis</h3>
                <div className="h-96">
                  <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart data={chartData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="name" />
                      <YAxis yAxisId="left" tickFormatter={(value) => formatCurrency(value).replace('₱', '')} />
                      <YAxis yAxisId="right" orientation="right" tickFormatter={(value) => `${value}%`} />
                      <Tooltip 
                        formatter={(value, name) => {
                          if (name === 'collected' || name === 'target') {
                            return [formatCurrency(value), name === 'collected' ? 'Collected' : 'Target'];
                          }
                          if (name === 'rate') {
                            return [`${value.toFixed(1)}%`, 'Collection Rate'];
                          }
                          return [value, name];
                        }}
                      />
                      <Legend />
                      <Bar yAxisId="left" dataKey="collected" fill="#3b82f6" name="Collected" radius={[4, 4, 0, 0]} />
                      <Line yAxisId="right" type="monotone" dataKey="rate" stroke="#10b981" strokeWidth={2} name="Collection Rate %" />
                    </ComposedChart>
                  </ResponsiveContainer>
                </div>
              </div>

              {/* Anomaly Distribution Radar Chart */}
              <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-gray-900 mb-6">Anomaly Distribution by Month</h3>
                <div className="h-96">
                  <ResponsiveContainer width="100%" height="100%">
                    <RadarChart data={chartData.filter(d => d.isAnomaly)}>
                      <PolarGrid />
                      <PolarAngleAxis dataKey="name" />
                      <PolarRadiusAxis />
                      <Radar
                        name="Anomaly Severity"
                        dataKey="anomalyValue"
                        stroke="#ef4444"
                        fill="#ef4444"
                        fillOpacity={0.6}
                      />
                      <Tooltip formatter={(value) => formatCurrency(value)} />
                      <Legend />
                    </RadarChart>
                  </ResponsiveContainer>
                </div>
              </div>
            </div>

            {/* Monthly Variance Analysis */}
            <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
              <h3 className="text-lg font-semibold text-gray-900 mb-6">Monthly Variance Analysis</h3>
              <div className="h-80">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                    <XAxis dataKey="name" />
                    <YAxis tickFormatter={(value) => `${value}%`} />
                    <Tooltip formatter={(value) => [`${value}%`, 'Variance']} />
                    <Legend />
                    <Bar 
                      dataKey="variance" 
                      fill="#8b5cf6" 
                      radius={[4, 4, 0, 0]}
                      name="Variance from Target"
                    >
                      {chartData.map((entry, index) => (
                        <Cell 
                          key={`cell-${index}`} 
                          fill={entry.isAnomaly ? 
                            (entry.anomalySeverity === 'high' ? '#ef4444' : 
                             entry.anomalySeverity === 'medium' ? '#f59e0b' : '#3b82f6') : 
                            '#8b5cf6'}
                        />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </div>
          </div>
        )}

        {/* Table View */}
        {viewMode === 'table' && (
          <div className="space-y-8">
            {/* Alerts Table */}
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
              <div className="p-6 border-b border-gray-200">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900">Anomaly Alerts</h3>
                    <p className="text-sm text-gray-500">Detected anomalies and their details</p>
                  </div>
                  <div className="flex flex-wrap items-center gap-3">
                    {/* Alert Filter */}
                    <div className="flex gap-1 bg-gray-100 p-1 rounded-lg">
                      {[
                        { id: 'all', label: 'All' },
                        { id: 'active', label: 'Active' },
                        { id: 'resolved', label: 'Resolved' },
                        { id: 'high', label: 'High' },
                        { id: 'medium', label: 'Medium' },
                        { id: 'low', label: 'Low' }
                      ].map((filter) => (
                        <button
                          key={filter.id}
                          onClick={() => setAlertFilter(filter.id)}
                          className={`px-3 py-1 text-sm rounded-md transition-colors ${
                            alertFilter === filter.id
                              ? 'bg-white shadow-sm text-gray-900'
                              : 'text-gray-600 hover:text-gray-900'
                          }`}
                        >
                          {filter.label}
                        </button>
                      ))}
                    </div>
                    
                    {/* Items per page */}
                    <select
                      value={itemsPerPage}
                      onChange={(e) => {
                        setItemsPerPage(Number(e.target.value));
                        setCurrentPage(1);
                      }}
                      className="px-3 py-1 border border-gray-300 rounded-lg text-sm"
                    >
                      <option value="5">5 per page</option>
                      <option value="10">10 per page</option>
                      <option value="25">25 per page</option>
                      <option value="50">50 per page</option>
                    </select>
                    
                    {/* Resolve All Button */}
                    <button
                      onClick={resolveAllAlerts}
                      className="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:opacity-90 flex items-center gap-2"
                    >
                      <CheckCircle className="w-4 h-4" />
                      <span>Resolve All</span>
                    </button>
                  </div>
                </div>
              </div>
              
              {/* Table */}
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">System</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deviation</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {paginatedAlerts.length > 0 ? (
                      paginatedAlerts.map((alert) => (
                        <tr key={alert.id} className="hover:bg-gray-50">
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="flex items-center">
                              <div className={`w-3 h-3 rounded-full mr-2 ${
                                alert.isResolved ? 'bg-green-500' : 'bg-red-500 animate-pulse'
                              }`}></div>
                              <span className="text-sm">
                                {alert.isResolved ? 'Resolved' : 'Active'}
                              </span>
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">{alert.month}</div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <div 
                                className="w-2 h-2 rounded-full"
                                style={{ backgroundColor: getSystemColor(alert.system) }}
                              ></div>
                              <span className="text-sm text-gray-900">{alert.system}</span>
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm font-bold text-gray-900">
                              {formatCurrency(alert.value)}
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${
                              alert.severity === 'high' ? 'bg-red-100 text-red-800' :
                              alert.severity === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                              'bg-blue-100 text-blue-800'
                            }`}>
                              {alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}
                            </span>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{alert.method.toUpperCase()}</div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="flex items-center gap-1">
                              {alert.isBelow ? (
                                <TrendingDownIcon className="w-4 h-4 text-red-500" />
                              ) : (
                                <TrendingUpIcon className="w-4 h-4 text-green-500" />
                              )}
                              <span className={`text-sm font-medium ${
                                alert.isBelow ? 'text-red-600' : 'text-green-600'
                              }`}>
                                {alert.deviation || 'N/A'}
                              </span>
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <button
                                onClick={() => toggleDetails(alert.id)}
                                className="text-gray-600 hover:text-gray-900"
                              >
                                {showDetails[alert.id] ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                              </button>
                              {!alert.isResolved && (
                                <button
                                  onClick={() => resolveAlert(alert.id)}
                                  className="px-3 py-1 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-sm"
                                >
                                  Resolve
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="8" className="py-12 text-center">
                          <div className="flex flex-col items-center justify-center">
                            <CheckCircle className="w-12 h-12 text-green-500 mb-4" />
                            <p className="text-gray-900 font-medium">No alerts found</p>
                            <p className="text-gray-500 text-sm mt-1">
                              {alerts.length > 0 ? 'Try changing your filter settings' : 'No anomalies detected'}
                            </p>
                          </div>
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
              
              {/* Pagination */}
              {totalPages > 1 && (
                <div className="px-6 py-4 border-t border-gray-200">
                  <div className="flex items-center justify-between">
                    <div className="text-sm text-gray-700">
                      Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filteredAlerts.length)} of {filteredAlerts.length} alerts
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                        disabled={currentPage === 1}
                        className="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                      >
                        <ChevronLeft className="w-4 h-4" />
                      </button>
                      <div className="flex items-center gap-1">
                        {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                          let pageNum;
                          if (totalPages <= 5) {
                            pageNum = i + 1;
                          } else if (currentPage <= 3) {
                            pageNum = i + 1;
                          } else if (currentPage >= totalPages - 2) {
                            pageNum = totalPages - 4 + i;
                          } else {
                            pageNum = currentPage - 2 + i;
                          }
                          return (
                            <button
                              key={pageNum}
                              onClick={() => setCurrentPage(pageNum)}
                              className={`w-8 h-8 rounded-lg text-sm ${
                                currentPage === pageNum
                                  ? 'bg-gray-900 text-white'
                                  : 'hover:bg-gray-100'
                              }`}
                            >
                              {pageNum}
                            </button>
                          );
                        })}
                      </div>
                      <button
                        onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                        disabled={currentPage === totalPages}
                        className="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
                      >
                        <ChevronRightIcon className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Monthly Data Table */}
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
              <div className="p-6 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">Monthly Collection Data</h3>
                <p className="text-sm text-gray-500">Detailed breakdown by month</p>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collected</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                      <th className="py-3 px-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Anomaly</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200">
                    {monthlyData.map((month, index) => {
                      const isAnomaly = alerts.some(a => a.month === month.month_name && !a.isResolved);
                      const alert = alerts.find(a => a.month === month.month_name && !a.isResolved);
                      const collectionRate = month.target_amount > 0 ? 
                        (month.collected_amount / month.target_amount) * 100 : 0;
                      
                      return (
                        <tr key={index} className="hover:bg-gray-50">
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm font-medium text-gray-900">{month.month_name}</div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm font-bold text-gray-900">
                              {formatCurrency(month.collected_amount)}
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{formatCurrency(month.target_amount)}</div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <div className="w-16 bg-gray-200 rounded-full h-2">
                                <div 
                                  className="h-2 rounded-full bg-green-500" 
                                  style={{ width: `${Math.min(100, collectionRate)}%` }}
                                ></div>
                              </div>
                              <span className="text-sm font-medium">
                                {collectionRate.toFixed(1)}%
                              </span>
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className="text-sm text-gray-900">{month.transaction_count}</div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            <div className={`text-sm font-medium ${
                              month.variance >= 0 ? 'text-green-600' : 'text-red-600'
                            }`}>
                              {month.variance >= 0 ? '+' : ''}{month.variance || 0}%
                            </div>
                          </td>
                          <td className="py-4 px-6 whitespace-nowrap">
                            {isAnomaly ? (
                              <span className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium ${
                                alert.severity === 'high' ? 'bg-red-100 text-red-800' :
                                alert.severity === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                                'bg-blue-100 text-blue-800'
                              }`}>
                                <AlertTriangle className="w-3 h-3" />
                                {alert.severity}
                              </span>
                            ) : (
                              <span className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                <CheckCircle className="w-3 h-3 mr-1" />
                                Normal
                              </span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}