import React, { useState, useEffect } from 'react';
import { 
  TrendingUp, TrendingDown, AlertTriangle, 
  DollarSign, Building, Store, Home,
  Calendar, Download, RefreshCw, Brain,
  Activity, Zap, Shield, Target,
  ArrowUp, ArrowDown, AlertCircle,
  CheckCircle, Clock, BarChart3,
  PieChart, LineChart, Users,
  MapPin, Bell, Settings, X
} from 'lucide-react';

export default function Anomaly() {
  const [anomalies, setAnomalies] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeSystem, setActiveSystem] = useState('all');
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [lastUpdated, setLastUpdated] = useState(new Date());
  const [selectedAnomaly, setSelectedAnomaly] = useState(null);

  // Color palette
  const colors = {
    primary: '#4a90e2',    // Blue
    secondary: '#9aa5b1',   // Gray
    success: '#4caf50',     // Green
    background: '#fbfbfb'   // Off-white
  };

  useEffect(() => {
    fetchAnomalies();
    
    if (autoRefresh) {
      const interval = setInterval(fetchAnomalies, 30000);
      return () => clearInterval(interval);
    }
  }, [autoRefresh]);

  const fetchAnomalies = async () => {
    try {
      const response = await fetch('http://localhost/revenue2/backend/Treasury/anomaly_detection.php?action=detect');
      const data = await response.json();
      setAnomalies(data);
      setLastUpdated(new Date());
    } catch (error) {
      console.error('Error fetching anomalies:', error);
    } finally {
      setLoading(false);
    }
  };

  const getSystemIcon = (system) => {
    switch(system) {
      case 'Business Tax': return <Building className="w-5 h-5" style={{ color: colors.primary }} />;
      case 'Real Property Tax': return <Home className="w-5 h-5" style={{ color: colors.primary }} />;
      case 'Market Rent': return <Store className="w-5 h-5" style={{ color: colors.primary }} />;
      default: return <Activity className="w-5 h-5" style={{ color: colors.secondary }} />;
    }
  };

  const getSeverityColor = (severity) => {
    switch(severity) {
      case 'critical': 
        return 'bg-red-50 text-red-700 border-red-200';
      case 'warning': 
        return 'bg-yellow-50 text-yellow-700 border-yellow-200';
      default: 
        return 'bg-blue-50 text-blue-700 border-blue-200';
    }
  };

  const getSeverityDarkColor = (severity) => {
    switch(severity) {
      case 'critical': 
        return 'dark:bg-red-900/20 dark:text-red-400 dark:border-red-800';
      case 'warning': 
        return 'dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800';
      default: 
        return 'dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800';
    }
  };

  const getTypeColor = (type) => {
    if (type.includes('SPIKE') || type.includes('SURGE')) return 'text-green-600 dark:text-green-400';
    if (type.includes('DROP') || type.includes('LOW')) return 'text-red-600 dark:text-red-400';
    return 'text-yellow-600 dark:text-yellow-400';
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    }).format(amount);
  };

  const filteredAnomalies = () => {
    if (!anomalies) return [];
    
    if (activeSystem === 'all') {
      return [
        ...(anomalies.business || []),
        ...(anomalies.rpt || []),
        ...(anomalies.market || [])
      ].sort((a, b) => new Date(b.date) - new Date(a.date));
    }
    
    return anomalies[activeSystem] || [];
  };

  const getAnomalyStats = () => {
    if (!anomalies) return { critical: 0, warning: 0, total: 0 };
    
    const all = [
      ...(anomalies.business || []),
      ...(anomalies.rpt || []),
      ...(anomalies.market || [])
    ];
    
    return {
      critical: all.filter(a => a.severity === 'critical').length,
      warning: all.filter(a => a.severity === 'warning').length,
      total: all.length
    };
  };

  const stats = getAnomalyStats();

  return (
    <div 
      className="mx-1 mt-1 p-6 rounded-lg min-h-screen"
      style={{ 
        backgroundColor: colors.background,
        color: '#1e293b'
      }}
    >
      {/* Header with AI Status */}
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
              AI Anomaly Detection
            </h1>
            <p style={{ color: colors.secondary }}>
              Real-time monitoring with machine learning pattern recognition
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
            <RefreshCw className="w-5 h-5" />
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

      {/* AI Status Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div 
          className="rounded-lg p-4 text-white"
          style={{ 
            background: `linear-gradient(135deg, ${colors.primary} 0%, #357abd 100%)`
          }}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm opacity-90">AI Confidence</p>
              <p className="text-2xl font-bold mt-1">87%</p>
              <p className="text-xs opacity-75 mt-1">Pattern recognition accuracy</p>
            </div>
            <Brain className="w-10 h-10 opacity-80" />
          </div>
        </div>

        <div 
          className="rounded-lg p-4 border"
          style={{ 
            backgroundColor: '#fef2f2',
            borderColor: '#fee2e2',
            color: '#991b1b'
          }}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm">Critical Anomalies</p>
              <p className="text-2xl font-bold mt-1">{stats.critical}</p>
              <p className="text-xs mt-1 opacity-75">Requires immediate attention</p>
            </div>
            <AlertTriangle className="w-10 h-10" style={{ color: '#dc2626' }} />
          </div>
        </div>

        <div 
          className="rounded-lg p-4 border"
          style={{ 
            backgroundColor: '#fefce8',
            borderColor: '#fef08a',
            color: '#854d0e'
          }}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm">Warning Anomalies</p>
              <p className="text-2xl font-bold mt-1">{stats.warning}</p>
              <p className="text-xs mt-1 opacity-75">Monitor closely</p>
            </div>
            <AlertCircle className="w-10 h-10" style={{ color: '#ca8a04' }} />
          </div>
        </div>

        <div 
          className="rounded-lg p-4 border"
          style={{ 
            backgroundColor: `${colors.primary}10`,
            borderColor: colors.primary,
            color: colors.primary
          }}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm">AI Predictions</p>
              <p className="text-lg font-bold mt-1">Next Month</p>
              <p className="text-xs mt-1" style={{ color: colors.primary }}>
                {anomalies?.ai_insights?.revenue_prediction?.next_month?.total 
                  ? formatCurrency(anomalies.ai_insights.revenue_prediction.next_month.total)
                  : '₱0.00'}
              </p>
            </div>
            <Target className="w-10 h-10" style={{ color: colors.primary }} />
          </div>
        </div>
      </div>

      {/* System Filters */}
      <div className="flex items-center gap-2 mb-6 overflow-x-auto pb-2">
        <button
          onClick={() => setActiveSystem('all')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
          style={{ 
            backgroundColor: activeSystem === 'all' ? colors.primary : `${colors.secondary}20`,
            color: activeSystem === 'all' ? 'white' : colors.secondary
          }}
        >
          <Activity className="w-4 h-4" />
          All Systems
        </button>
        
        <button
          onClick={() => setActiveSystem('business')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
          style={{ 
            backgroundColor: activeSystem === 'business' ? colors.primary : `${colors.secondary}20`,
            color: activeSystem === 'business' ? 'white' : colors.secondary
          }}
        >
          <Building className="w-4 h-4" />
          Business Tax
        </button>
        
        <button
          onClick={() => setActiveSystem('rpt')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
          style={{ 
            backgroundColor: activeSystem === 'rpt' ? colors.primary : `${colors.secondary}20`,
            color: activeSystem === 'rpt' ? 'white' : colors.secondary
          }}
        >
          <Home className="w-4 h-4" />
          Real Property Tax
        </button>
        
        <button
          onClick={() => setActiveSystem('market')}
          className="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
          style={{ 
            backgroundColor: activeSystem === 'market' ? colors.primary : `${colors.secondary}20`,
            color: activeSystem === 'market' ? 'white' : colors.secondary
          }}
        >
          <Store className="w-4 h-4" />
          Market Rent
        </button>

        <div className="flex-1" />

        <button 
          className="p-2 rounded-lg transition-colors"
          style={{ 
            backgroundColor: `${colors.secondary}20`,
            color: colors.secondary
          }}
        >
          <Download className="w-5 h-5" />
        </button>
        
        <button 
          className="p-2 rounded-lg transition-colors"
          style={{ 
            backgroundColor: `${colors.secondary}20`,
            color: colors.secondary
          }}
        >
          <Settings className="w-5 h-5" />
        </button>
      </div>

      {/* AI Insights Panel */}
      {anomalies?.ai_insights && (
        <div 
          className="mb-6 p-5 rounded-lg border"
          style={{ 
            backgroundColor: `${colors.primary}08`,
            borderColor: colors.primary
          }}
        >
          <div className="flex items-start gap-3">
            <div 
              className="p-2 rounded-lg"
              style={{ backgroundColor: `${colors.primary}20` }}
            >
              <Brain className="w-5 h-5" style={{ color: colors.primary }} />
            </div>
            <div className="flex-1">
              <h3 className="font-semibold mb-2 flex items-center gap-2" style={{ color: colors.primary }}>
                AI-Generated Insights
                <span 
                  className="text-xs px-2 py-1 rounded-full"
                  style={{ 
                    backgroundColor: `${colors.primary}20`,
                    color: colors.primary
                  }}
                >
                  {anomalies.ai_insights.revenue_prediction.next_month.confidence} confidence
                </span>
              </h3>
              
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                <div 
                  className="p-3 rounded-lg"
                  style={{ backgroundColor: 'white' }}
                >
                  <p className="text-sm" style={{ color: colors.secondary }}>Revenue Prediction</p>
                  <p className="text-lg font-bold" style={{ color: colors.primary }}>
                    {formatCurrency(anomalies.ai_insights.revenue_prediction.next_month.total)}
                  </p>
                  <p className="text-xs mt-1" style={{ color: colors.secondary }}>
                    Next month forecast
                  </p>
                </div>
                
                <div 
                  className="p-3 rounded-lg"
                  style={{ backgroundColor: 'white' }}
                >
                  <p className="text-sm" style={{ color: colors.secondary }}>Trend Direction</p>
                  <div className="flex items-center gap-2 mt-1">
                    {anomalies.ai_insights.revenue_prediction.trend.direction === 'positive' ? (
                      <>
                        <TrendingUp className="w-5 h-5" style={{ color: colors.success }} />
                        <span className="font-medium" style={{ color: colors.success }}>Increasing</span>
                      </>
                    ) : anomalies.ai_insights.revenue_prediction.trend.direction === 'negative' ? (
                      <>
                        <TrendingDown className="w-5 h-5" style={{ color: '#dc2626' }} />
                        <span className="font-medium" style={{ color: '#dc2626' }}>Decreasing</span>
                      </>
                    ) : (
                      <>
                        <Activity className="w-5 h-5" style={{ color: '#ca8a04' }} />
                        <span className="font-medium" style={{ color: '#ca8a04' }}>Stable</span>
                      </>
                    )}
                  </div>
                </div>
                
                <div 
                  className="p-3 rounded-lg"
                  style={{ backgroundColor: 'white' }}
                >
                  <p className="text-sm" style={{ color: colors.secondary }}>Risk Assessment</p>
                  <div className="flex items-center gap-2 mt-1">
                    <Shield style={{ 
                      color: anomalies.ai_insights.risk_assessment.overall_risk_level === 'low' ? colors.success :
                             anomalies.ai_insights.risk_assessment.overall_risk_level === 'moderate' ? '#ca8a04' :
                             '#dc2626'
                    }} />
                    <span className="font-medium capitalize">
                      {anomalies.ai_insights.risk_assessment.overall_risk_level}
                    </span>
                    <span 
                      className="text-xs px-2 py-1 rounded-full"
                      style={{ 
                        backgroundColor: `${colors.secondary}20`,
                        color: colors.secondary
                      }}
                    >
                      Score: {anomalies.ai_insights.risk_assessment.risk_score}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Anomalies List */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" style={{ color: '#ca8a04' }} />
            Detected Anomalies
            <span 
              className="text-sm px-2 py-1 rounded-full"
              style={{ 
                backgroundColor: `${colors.secondary}20`,
                color: colors.secondary
              }}
            >
              {filteredAnomalies().length} found
            </span>
          </h2>
          
          <select 
            className="text-sm border rounded-lg px-3 py-2"
            style={{ 
              borderColor: colors.secondary,
              backgroundColor: 'white'
            }}
          >
            <option>All Severities</option>
            <option>Critical Only</option>
            <option>Warning Only</option>
          </select>
        </div>

        {loading ? (
          <div className="flex items-center justify-center py-12">
            <div className="relative">
              <div 
                className="w-12 h-12 border-4 rounded-full animate-spin"
                style={{ 
                  borderColor: `${colors.secondary}20`,
                  borderTopColor: colors.primary
                }}
              ></div>
              <div className="absolute inset-0 flex items-center justify-center">
                <Brain className="w-5 h-5 animate-pulse" style={{ color: colors.primary }} />
              </div>
            </div>
          </div>
        ) : filteredAnomalies().length === 0 ? (
          <div 
            className="text-center py-12 rounded-lg"
            style={{ backgroundColor: `${colors.secondary}08` }}
          >
            <CheckCircle className="w-12 h-12 mx-auto mb-3" style={{ color: colors.success }} />
            <p style={{ color: colors.secondary }}>No anomalies detected</p>
            <p className="text-sm mt-1" style={{ color: colors.secondary }}>
              All systems operating normally
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4">
            {filteredAnomalies().map((anomaly, index) => (
              <div
                key={index}
                onClick={() => setSelectedAnomaly(anomaly)}
                className={`rounded-lg border p-4 hover:shadow-md transition-all cursor-pointer
                  ${getSeverityColor(anomaly.severity)} ${getSeverityDarkColor(anomaly.severity)}`}
              >
                <div className="flex items-start gap-4">
                  <div 
                    className="p-2 rounded-lg"
                    style={{ 
                      backgroundColor: anomaly.severity === 'critical' ? '#fee2e2' :
                                     anomaly.severity === 'warning' ? '#fef9c3' :
                                     `${colors.primary}20`
                    }}
                  >
                    {getSystemIcon(anomaly.system)}
                  </div>
                  
                  <div className="flex-1">
                    <div className="flex items-start justify-between">
                      <div>
                        <div className="flex items-center gap-2 mb-1">
                          <span 
                            className={`text-xs font-medium px-2 py-1 rounded-full ${
                              anomaly.severity === 'critical' ? 'bg-red-100 text-red-800' :
                              anomaly.severity === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                              'bg-blue-100 text-blue-800'
                            }`}
                          >
                            {anomaly.severity?.toUpperCase()}
                          </span>
                          <span className={`text-sm font-medium ${getTypeColor(anomaly.type)}`}>
                            {anomaly.type?.replace(/_/g, ' ')}
                          </span>
                        </div>
                        
                        <h3 className="font-medium text-lg mb-1">
                          {anomaly.system}: {anomaly.description}
                        </h3>
                        
                        <div className="flex flex-wrap items-center gap-4 mt-2 text-sm">
                          {anomaly.date && (
                            <span className="flex items-center gap-1" style={{ color: colors.secondary }}>
                              <Calendar className="w-4 h-4" />
                              {new Date(anomaly.date).toLocaleDateString()}
                            </span>
                          )}
                          
                          {anomaly.change_percent && (
                            <span className={`flex items-center gap-1 font-medium ${
                              anomaly.change_percent > 0 ? 'text-green-600' : 'text-red-600'
                            }`}>
                              {anomaly.change_percent > 0 ? (
                                <ArrowUp className="w-4 h-4" />
                              ) : (
                                <ArrowDown className="w-4 h-4" />
                              )}
                              {Math.abs(anomaly.change_percent)}% variance
                            </span>
                          )}
                          
                          {anomaly.value && (
                            <span className="flex items-center gap-1" style={{ color: colors.secondary }}>
                              <DollarSign className="w-4 h-4" />
                              {formatCurrency(anomaly.value)}
                            </span>
                          )}
                          
                          {anomaly.occupancy_rate && (
                            <span className="flex items-center gap-1" style={{ color: colors.secondary }}>
                              <Users className="w-4 h-4" />
                              Occupancy: {anomaly.occupancy_rate}
                            </span>
                          )}
                        </div>
                        
                        {anomaly.ai_analysis && (
                          <div 
                            className="mt-3 p-3 rounded-lg border"
                            style={{ 
                              backgroundColor: `${colors.primary}08`,
                              borderColor: colors.primary
                            }}
                          >
                            <div className="flex items-start gap-2">
                              <Brain className="w-4 h-4 mt-0.5 flex-shrink-0" style={{ color: colors.primary }} />
                              <p className="text-sm" style={{ color: colors.primary }}>
                                {anomaly.ai_analysis}
                              </p>
                            </div>
                          </div>
                        )}
                      </div>
                      
                      <button 
                        className="p-1 rounded-lg transition-colors hover:opacity-70"
                        style={{ color: colors.secondary }}
                      >
                        <X className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* AI Recommendations */}
      {anomalies?.ai_insights?.recommendations && anomalies.ai_insights.recommendations.length > 0 && (
        <div className="mt-8">
          <h2 className="text-lg font-semibold mb-4 flex items-center gap-2" style={{ color: colors.primary }}>
            <Target className="w-5 h-5" style={{ color: colors.primary }} />
            AI-Generated Recommendations
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {anomalies.ai_insights.recommendations.map((rec, index) => (
              <div
                key={index}
                className="bg-white rounded-lg border p-4"
                style={{ 
                  borderLeftWidth: '4px',
                  borderLeftColor: rec.priority === 'high' ? '#dc2626' : '#ca8a04'
                }}
              >
                <div className="flex items-start gap-3">
                  <div 
                    className="p-2 rounded-lg"
                    style={{ 
                      backgroundColor: rec.priority === 'high' ? '#fee2e2' : '#fef9c3'
                    }}
                  >
                    {rec.category === 'collection' ? 
                      <DollarSign className="w-5 h-5" style={{ color: rec.priority === 'high' ? '#dc2626' : '#ca8a04' }} /> :
                     rec.category === 'marketing' ? 
                      <Users className="w-5 h-5" style={{ color: rec.priority === 'high' ? '#dc2626' : '#ca8a04' }} /> :
                     rec.category === 'assessment' ? 
                      <Home className="w-5 h-5" style={{ color: rec.priority === 'high' ? '#dc2626' : '#ca8a04' }} /> :
                      <Activity className="w-5 h-5" style={{ color: rec.priority === 'high' ? '#dc2626' : '#ca8a04' }} />}
                  </div>
                  
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <span 
                        className={`text-xs font-medium px-2 py-1 rounded-full ${
                          rec.priority === 'high' 
                            ? 'bg-red-100 text-red-800'
                            : 'bg-yellow-100 text-yellow-800'
                        }`}
                      >
                        {rec.priority.toUpperCase()} PRIORITY
                      </span>
                      <span 
                        className="text-xs px-2 py-1 rounded-full"
                        style={{ 
                          backgroundColor: `${colors.primary}20`,
                          color: colors.primary
                        }}
                      >
                        AI Confidence: {rec.ai_confidence}
                      </span>
                    </div>
                    
                    <h3 className="font-medium mb-1">{rec.title}</h3>
                    <p className="text-sm mb-2" style={{ color: colors.secondary }}>
                      {rec.description}
                    </p>
                    <p className="text-xs" style={{ color: colors.secondary }}>
                      Potential impact: {rec.potential_impact}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Pattern Analysis */}
      {anomalies?.ai_insights?.pattern_recognition && (
        <div className="mt-8">
          <h2 className="text-lg font-semibold mb-4 flex items-center gap-2" style={{ color: colors.primary }}>
            <BarChart3 className="w-5 h-5" style={{ color: colors.primary }} />
            Pattern Recognition Analysis
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-white rounded-lg border p-4">
              <h3 className="text-sm font-medium mb-3 flex items-center gap-2" style={{ color: colors.secondary }}>
                <Calendar className="w-4 h-4" />
                Weekly Patterns
              </h3>
              <div className="space-y-2">
                {anomalies.ai_insights.pattern_recognition.weekly_patterns?.map((day, i) => (
                  <div key={i} className="flex items-center justify-between text-sm">
                    <span style={{ color: colors.secondary }}>
                      {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][day.day_of_week - 1]}
                    </span>
                    <span className="font-medium" style={{ color: colors.primary }}>
                      {formatCurrency(day.avg_payment)}
                    </span>
                  </div>
                ))}
              </div>
            </div>
            
            <div className="bg-white rounded-lg border p-4">
              <h3 className="text-sm font-medium mb-3 flex items-center gap-2" style={{ color: colors.secondary }}>
                <Clock className="w-4 h-4" />
                Monthly Patterns
              </h3>
              <div className="space-y-2">
                {anomalies.ai_insights.pattern_recognition.monthly_patterns?.map((month, i) => (
                  <div key={i} className="flex items-center justify-between text-sm">
                    <span style={{ color: colors.secondary }}>
                      Month {month.month}
                    </span>
                    <span className="font-medium" style={{ color: colors.primary }}>
                      {month.avg_days_late} days late
                    </span>
                  </div>
                ))}
              </div>
            </div>
            
            <div className="bg-white rounded-lg border p-4">
              <h3 className="text-sm font-medium mb-3 flex items-center gap-2" style={{ color: colors.secondary }}>
                <PieChart className="w-4 h-4" />
                Correlations
              </h3>
              <div className="space-y-2">
                {Object.entries(anomalies.ai_insights.pattern_recognition.correlations || {}).map(([key, value], i) => (
                  <div key={i} className="flex items-center justify-between text-sm">
                    <span className="capitalize" style={{ color: colors.secondary }}>
                      {key.replace(/_/g, ' ')}
                    </span>
                    <span className={`font-medium ${
                      parseFloat(value) > 0.7 ? 'text-green-600' :
                      parseFloat(value) > 0.4 ? 'text-yellow-600' :
                      'text-red-600'
                    }`}>
                      {value}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Dark mode styles */}
      <style jsx>{`
        @media (prefers-color-scheme: dark) {
          .dark-bg {
            background-color: #0f172a;
          }
          .dark-text {
            color: #e2e8f0;
          }
        }
      `}</style>
    </div>
  );
}