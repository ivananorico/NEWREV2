import { LayoutDashboard, Settings } from 'lucide-react'

const sidebarItems = [
  //{
    //id: "dashboard",
    //label: "Dashboard",
    //icon: LayoutDashboard,
    //path: "/dashboard",
  //},
  {
    id: "module1",
    label: "Real Property Tax Collection System",
    icon: LayoutDashboard,
    subItems: [
      { id: "rpt1", label: "RPT Dashboard", path: "/RPT/RPTDashboard" },
      { id: "rptanomaly", label: "RPT Delinquent", path: "/RPT/RPTDelinquent" },
      { id: "rpt2", label: "RPT Configuration", path: "/RPT/RPTConfig" },
      { id: "rpt3", label: "RPT Validation", path: "/RPT/RPTValidationTable" },
      { id: "rpt4", label: "RPT Status", path: "/RPT/RPTStatus" },

    ]
  },
  {
    id: "module2",
    label: "Business Tax and Regulatory Fee Payment",
    icon: LayoutDashboard,
    subItems: [
      { id: "BusinessTaxDashboard", label: "Business Tax Dashboard", path: "/BUSINESS/BusinessTaxDashboard" },
      { id: "BusinessAnomaly", label: "Business Deliquent", path: "/BUSINESS/BusinessDelinquent" },
      { id: "BusinessTaxConfig", label: "Business Tax Configuration", path: "/BUSINESS/BusinessTaxConfig" },
      { id: "BusinessValidation", label: "BusinessValidation", path: "/BUSINESS/BusinessValidation" },
      { id: "BusinessStatus", label: "Business Status", path: "/BUSINESS/BusinessStatus" },
    ]
  },
  {
    id: "module4",
    label: "Digital Payment Integration",
    icon: LayoutDashboard,
    subItems: [
      { id: "digidashboard", label: "Digital Dashboard", path: "/DIGITAL/DigiDashboard" },

    ]
  },
  {
    id: "module5",
    label: "Treasury Dashboard & Report",
    icon: LayoutDashboard,
    subItems: [
      { id: "RevenueCollection", label: "Revenue Collection", path: "/TREASURY/RevenueCollection" },
      { id: "Anomaly", label: "Anomaly Detection", path: "/TREASURY/Anomaly" },
      { id: "FundAllocation", label: "Fund Allocation", path: "/TREASURY/FundAllocation" },
    ]
  },

  {
    id: "module6",
    label: "Market Stall Rental and Billing",
    icon: LayoutDashboard,
    subItems: [
      { id: "market1", label: "Market Dashboard", path: "/MARKET/MarketDashboard" },
      { id: "marketanomaly", label: "Market Delinquent", path: "/MARKET/MarketDelinquent" },
      { id: "market2", label: "Market Stall Map Creator", path: "/MARKET/MapCreator" },
      { id: "market3", label: "Market Validation", path: "/MARKET/MarketValidation" },
      { id: "market4", label: "Market Status", path: "/MARKET/MarketStatus" },
    ]
  },
  //{
    //id: "settings",
    //label: "Settings",
   // icon: Settings,
   // subItems: [
    //  { id: "general-settings", label: "General", path: "/settings/general" },
     // { id: "security-settings", label: "Security", path: "/settings/security" }
   // ]
  //}
]

export default sidebarItems
