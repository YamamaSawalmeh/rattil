import { useState, useMemo } from "react";
import { 
  LayoutDashboard, 
  Sprout, 
  Users, 
  Calendar, 
  Trophy, 
  Settings, 
  LogOut, 
  Bell, 
  Search, 
  HelpCircle,
  Clock,
  ChevronLeft
} from "lucide-react";
import { motion, AnimatePresence } from "motion/react";

// --- Types ---
interface Tree {
  id: number;
  surah_name: string;
  type: "shrub" | "palm" | "oak";
  planted_at: string;
  short_surah?: boolean;
}

// --- Mock Data ---
const MOCK_TREES: Tree[] = [
  { id: 1, surah_name: "سورة البقرة", type: "palm", planted_at: "12 رمضان 1445" },
  { id: 2, surah_name: "سورة النبأ", type: "shrub", planted_at: "4 ذو الحجة 1445" },
  { id: 3, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 4, surah_name: "سورة البقرة", type: "palm", planted_at: "12 رمضان 1445" },
  { id: 5, surah_name: "سورة النبأ", type: "shrub", planted_at: "4 ذو الحجة 1445" },
  { id: 6, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 7, surah_name: "سورة البقرة", type: "palm", planted_at: "12 رمضان 1445" },
  { id: 8, surah_name: "سورة النبأ", type: "shrub", planted_at: "4 ذو الحجة 1445" },
  { id: 9, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 10, surah_name: "سورة البقرة", type: "palm", planted_at: "12 رمضان 1445" },
  { id: 11, surah_name: "سورة النبأ", type: "shrub", planted_at: "4 ذو الحجة 1445" },
  { id: 12, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 13, surah_name: "سورة البقرة", type: "palm", planted_at: "12 رمضان 1445" },
  { id: 14, surah_name: "سورة النبأ", type: "shrub", planted_at: "4 ذو الحجة 1445" },
  { id: 15, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 16, surah_name: "سورة البقرة", type: "palm", planted_at: "12 رمضان 1445" },
  { id: 17, surah_name: "سورة النبأ", type: "shrub", planted_at: "4 ذو الحجة 1445" },
  { id: 18, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 19, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 20, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
  { id: 21, surah_name: "سورة الكوثر", type: "shrub", planted_at: "1 ذو الحجة 1445", short_surah: true },
];

// --- Components ---

const Sidebar = () => {
  const menuItems = [
    { name: "لوحة التحكم", icon: LayoutDashboard, path: "#" },
    { name: "حديقة الإنجازات", icon: Sprout, path: "#", active: true },
    { name: "حلقاتي", icon: Users, path: "#" },
    { name: "جدول الحفظ", icon: Calendar, path: "#" },
    { name: "الإنجازات", icon: Trophy, path: "#" },
    { name: "الإعدادات", icon: Settings, path: "#" },
  ];

  return (
    <aside className="w-64 bg-primary-green flex flex-col h-screen fixed right-0 top-0 z-20 overflow-hidden shadow-2xl">
      <div className="p-8 flex items-center gap-3">
        <div className="w-12 h-12 bg-gold flex items-center justify-center text-primary-green rounded-2xl text-2xl shadow-lg shadow-gold/20">
          📖
        </div>
        <span className="text-3xl font-bold text-white font-amiri tracking-tight">رَتِّل</span>
      </div>

      <nav className="flex-1 px-4 py-8 space-y-3">
        {menuItems.map((item) => (
          <a
            key={item.name}
            href={item.path}
            className={`flex items-center gap-4 px-5 py-4 rounded-2xl transition-all duration-300 group ${
              item.active 
                ? "bg-white/10 text-white shadow-xl" 
                : "text-white/60 hover:opacity-100 hover:text-white"
            }`}
          >
            <item.icon size={22} className={item.active ? "text-gold" : "group-hover:scale-110 transition-transform"} />
            <span className="font-amiri text-xl">{item.name}</span>
          </a>
        ))}
      </nav>

      <div className="p-6 mt-auto">
        <div className="bg-white/5 rounded-[24px] p-4 border border-white/10 mb-4">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-full bg-gold flex items-center justify-center text-primary-green font-bold text-xl shadow-inner">
              أ
            </div>
            <div className="flex flex-col">
              <span className="text-white text-base font-bold">أحمد محمد</span>
              <span className="text-white/40 text-[10px] uppercase tracking-widest leading-none">طالب مجتهد</span>
            </div>
          </div>
        </div>
        <button className="w-full flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 text-white py-4 rounded-2xl transition-all border border-white/10 text-lg">
          <LogOut size={20} />
          <span className="font-amiri">تسجيل الخروج</span>
        </button>
      </div>
    </aside>
  );
};

const Header = () => (
  <header className="flex items-center justify-between py-6 px-12">
    <h1 className="text-4xl font-bold text-primary-green">لوحة الإنجازات</h1>
    
    <div className="flex items-center gap-6">
      <div className="flex items-center gap-3 bg-muted-green px-6 py-3 rounded-full border border-black/5 shadow-sm">
        <span className="text-gold text-2xl">⭐</span>
        <span className="font-bold text-xl">1,250 نقطة</span>
      </div>
      
      <div className="flex items-center gap-3">
        <button className="w-12 h-12 rounded-full bg-white border border-black/5 flex items-center justify-center relative hover:shadow-md transition-shadow">
          🔔
          <span className="absolute top-0 right-0 w-5 h-5 bg-[#D64D4D] text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white">3</span>
        </button>
      </div>
    </div>
  </header>
);

const StatsSection = ({ treesCount, shrubs, palms }: { treesCount: number; shrubs: number; palms: number }) => (
  <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12 px-12">
    <div className="col-span-2 bg-gradient-to-br from-primary-green to-secondary-green rounded-[40px] p-10 text-white relative shadow-xl overflow-hidden flex flex-col justify-between group">
      <div className="relative z-10">
        <div className="text-gold text-sm uppercase tracking-widest mb-2 font-bold opacity-90">المستوى الحالي</div>
        <h2 className="text-6xl font-bold mb-8">المستوى الرابع <span className="text-2xl font-normal opacity-70">- الحافظ المتمكن</span></h2>
        
        <div className="space-y-4">
          <div className="w-full bg-black/20 h-4 rounded-full overflow-hidden p-1 shadow-inner">
            <motion.div 
              initial={{ width: 0 }}
              animate={{ width: "72%" }}
              transition={{ duration: 1.5, ease: "easeOut" }}
              className="bg-gold h-full rounded-full shadow-[0_0_20px_rgba(212,175,55,0.4)]"
            />
          </div>
          <div className="text-base opacity-80 font-inter">باقي 250 نقطة للوصول للمستوى الخامس</div>
        </div>
      </div>
      <div className="absolute -left-16 -bottom-16 opacity-10 text-[280px] leading-none select-none pointer-events-none transition-transform duration-1000 group-hover:scale-110">🌿</div>
    </div>

    <div className="bg-soft-sand border border-black/[0.05] rounded-[40px] p-8 flex flex-col justify-center items-center gap-3 shadow-sm hover:shadow-md transition-all">
      <div className="text-6xl mb-2 drop-shadow-lg">🥇</div>
      <div className="text-primary-green font-bold text-3xl">وسام النخبة</div>
      <div className="text-center text-base text-[#7A8C75] max-w-[200px]">مُنح لك لالتزامك بالحضور لمدة 30 يوماً متتالية</div>
      <div className="mt-6 text-xs uppercase tracking-widest bg-primary-green/5 px-6 py-2 rounded-full font-bold border border-primary-green/10">من: أ. عمر الخالد</div>
    </div>
  </div>
);

const TreeIcon = ({ type, id, isHovered }: { type: Tree["type"]; id: number; isHovered: boolean }) => {
  // Unique animation settings for each tree to prevent perfectly synchronized swaying
  const swayDuration = useMemo(() => 3 + (id % 3) * 0.5, [id]);
  const swayAmount = useMemo(() => 2 + (id % 2), [id]);

  const treeContent = () => {
    switch (type) {
      case "palm":
        return <span className="text-6xl" style={{ filter: "drop-shadow(0 10px 15px rgba(0,0,0,0.15))" }}>🌴</span>;
      case "oak":
        return <span className="text-6xl" style={{ filter: "drop-shadow(0 10px 15px rgba(0,0,0,0.15))" }}>🌳</span>;
      default:
        return <span className="text-6xl" style={{ filter: "drop-shadow(0 10px 15px rgba(0,0,0,0.15))" }}>🌿</span>;
    }
  };

  const groundDecor = useMemo(() => {
    const items = [];
    const seed = id * 13;
    if (seed % 3 === 0) items.push({ icon: "🌸", x: -20, y: 15, size: "text-[10px]" });
    if (seed % 2 === 0) items.push({ icon: "🌱", x: 15, y: 10, size: "text-[12px]" });
    if (seed % 5 === 0) items.push({ icon: "🌻", x: 0, y: 18, size: "text-[10px]" });
    return items;
  }, [id]);

  return (
    <div className="relative flex items-center justify-center">
      {/* Ground Decor */}
      <div className="absolute inset-0 pointer-events-none">
        {groundDecor.map((decor, i) => (
          <motion.span
            key={i}
            className={`absolute ${decor.size} opacity-80`}
            style={{ left: `calc(50% + ${decor.x}px)`, top: `calc(50% + ${decor.y}px)` }}
            animate={{ scale: isHovered ? 1.2 : 1, y: isHovered ? -2 : 0 }}
          >
            {decor.icon}
          </motion.span>
        ))}
      </div>

      {/* Main Tree with Sway */}
      <motion.div
        animate={{ 
          rotate: [(-swayAmount), swayAmount, (-swayAmount)],
          scale: isHovered ? 1.1 : 1
        }}
        transition={{ 
          rotate: { duration: swayDuration, repeat: Infinity, ease: "easeInOut" },
          scale: { duration: 0.3 }
        }}
        className="relative z-10 select-none cursor-pointer"
      >
        {treeContent()}
      </motion.div>

      {/* Subtle Shadow on Island */}
      <div className="absolute -bottom-1 w-12 h-4 bg-black/10 blur-md rounded-[100%] z-0"></div>
    </div>
  );
};

const GardenTile = ({ tree, index, hovered, onHover }: { tree: Tree; index: number; hovered: boolean; onHover: (id: number | null) => void }) => {
  const row = Math.floor(index / 4);
  const col = index % 4;
  
  const randomOffset = useMemo(() => ({
    x: (Math.random() - 0.5) * 30,
    y: (Math.random() - 0.5) * 20,
    h: Math.random() * 25,
    scale: 0.9 + Math.random() * 0.2 // Size variation
  }), []);

  const x = col * 200 + (row % 2 === 0 ? 0 : 100) + randomOffset.x;
  const y = row * 140 + randomOffset.y;

  return (
    <motion.div
      className="absolute cursor-pointer"
      style={{ left: x, top: y }}
      initial={{ opacity: 0, y: 50, scale: 0.8 }}
      animate={{ opacity: 1, y: 0, scale: randomOffset.scale }}
      transition={{ 
        type: "spring", 
        stiffness: 100, 
        delay: index * 0.04 
      }}
      whileHover={{ y: -15, scale: randomOffset.scale * 1.1 }}
      onMouseEnter={() => onHover(tree.id)}
      onMouseLeave={() => onHover(null)}
    >
      <div className="relative">
        <div className="absolute top-16 left-6 w-36 h-36 bg-black/10 rounded-full blur-2xl -rotate-45"></div>
        <div className="absolute top-6 left-0 w-36 h-36 bg-[#8c9c75] rounded-[40px] -rotate-45 border-b-[16px] border-black/15"></div>
        <div 
          className="w-36 h-36 bg-tile-grass rounded-[40px] -rotate-45 relative flex items-center justify-center border-t-2 border-white/40 shadow-xl"
          style={{ transform: `translateY(-${randomOffset.h}px) rotate(-45deg)` }}
        >
          <div className="w-28 h-28 bg-primary-green/[0.03] rounded-[32px] border border-black/5"></div>
        </div>
      </div>

      <div 
        className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none"
        style={{ transform: `translateY(-${45 + randomOffset.h}px)` }}
      >
        <div className="mb-6">
          <TreeIcon type={tree.type} id={tree.id} isHovered={hovered} />
        </div>
        <div className="bg-white/95 backdrop-blur-md px-4 py-2 rounded-2xl shadow-2xl border border-black/5 min-w-[120px] text-center transform scale-90 hover:scale-100 transition-transform">
          <div className="text-sm font-bold text-primary-green leading-tight">{tree.surah_name}</div>
          <div className="text-[10px] text-black/40 mt-1 font-inter">{tree.planted_at}</div>
        </div>
      </div>

      <AnimatePresence>
        {hovered && (
          <motion.div
            initial={{ opacity: 0, scale: 0.9, y: 10 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.9, y: 10 }}
            className="absolute -top-40 left-full -ml-4 z-40"
          >
            <div className="bg-white rounded-3xl p-6 shadow-2xl border border-black/5 w-64 relative overflow-hidden">
              <div className="absolute top-0 right-0 w-24 h-24 bg-muted-green/20 rounded-full -mr-12 -mt-12"></div>
              <div className="font-bold text-xl mb-2 text-primary-green">{tree.surah_name}</div>
              <div className="flex items-center gap-2 text-xs text-[#7A8C75] mb-3 bg-muted-green/30 w-fit px-3 py-1 rounded-full">
                <Clock size={14} />
                <span>سورة {tree.short_surah ? "قصيرة" : "متوسطة"}</span>
              </div>
              <div className="flex items-center justify-between text-xs mb-4">
                <div className="flex items-center gap-1.5 text-black/60">
                  <Calendar size={14} />
                  <span className="font-inter">{tree.planted_at}</span>
                </div>
                <div className="text-gold font-bold font-inter tracking-wider">#R{1200 + tree.id}</div>
              </div>
              <div className="text-sm text-primary-green/80 leading-relaxed bg-soft-sand p-4 rounded-2xl border border-black/5 italic">
                تم إتمام تلاوة وحفظ هذه السورة المباركة بحمد الله.
              </div>
              <div className="absolute top-full -mt-2 left-6">
                <svg width="32" height="16" viewBox="0 0 32 16" className="text-white fill-current drop-shadow-sm">
                   <path d="M16 16L0 0H32L16 16Z" />
                </svg>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </motion.div>
  );
};

export default function App() {
  const [hoveredId, setHoveredId] = useState<number | null>(null);
  const [timeFilter, setTimeFilter] = useState<"day" | "week" | "month" | "year">("week");

  const stats = useMemo(() => {
    return {
      shrubs: MOCK_TREES.filter(t => t.type === "shrub").length,
      palms: MOCK_TREES.filter(t => t.type === "palm").length,
      oaks: MOCK_TREES.filter(t => t.type === "oak").length,
    };
  }, []);

  const filters = [
    { id: "day", name: "يومي" },
    { id: "week", name: "أسبوعي" },
    { id: "month", name: "شهري" },
    { id: "year", name: "سنوي" },
  ];

  return (
    <div className="flex min-h-screen bg-cream font-amiri overflow-hidden">
      <Sidebar />
      
      <main className="flex-1 mr-64 flex flex-col h-screen overflow-hidden">
        <Header />
        
        <div className="flex-1 overflow-auto custom-scrollbar flex flex-col pt-8">
          {/* Header Section like Forest */}
          <div className="px-12 flex items-end justify-between mb-8">
            <div className="flex flex-col gap-2">
              <h1 className="text-5xl font-bold text-primary-green flex items-center gap-4">
                حديقتك <span className="text-3xl text-gold font-normal">🌳</span>
              </h1>
              <p className="text-xl text-black/50">شاهد ثمرة حفظك وتفوقك في كتاب الله</p>
            </div>

            <div className="bg-soft-sand/50 p-1.5 rounded-2xl border border-black/5 flex gap-1">
              {filters.map((f) => (
                <button
                  key={f.id}
                  onClick={() => setTimeFilter(f.id as any)}
                  className={`px-8 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ${
                    timeFilter === f.id 
                      ? "bg-primary-green text-white shadow-lg" 
                      : "text-black/40 hover:bg-black/5"
                  }`}
                >
                  {f.name}
                </button>
              ))}
            </div>
          </div>

          <div className="relative flex-1 min-h-[600px] flex items-center justify-center p-12">
            {/* The Main Island */}
            <div className="relative w-full h-full max-w-5xl flex items-center justify-center">
              
              {/* Cloud Decor */}
              <motion.div 
                animate={{ x: [0, 50, 0] }} 
                transition={{ duration: 20, repeat: Infinity, ease: "linear" }}
                className="absolute top-0 right-10 text-6xl opacity-20 pointer-events-none"
              >
                ☁️
              </motion.div>
              <motion.div 
                animate={{ x: [0, -40, 0] }} 
                transition={{ duration: 25, repeat: Infinity, ease: "linear" }}
                className="absolute bottom-20 left-0 text-5xl opacity-15 pointer-events-none"
              >
                ☁️
              </motion.div>

              {/* The Floating Island Base */}
              <div className="absolute inset-0 flex items-center justify-center pointer-events-none floating-island">
                <div className="w-[800px] h-[500px] bg-gradient-to-br from-[#8c9c75] to-[#5d6a4a] rounded-[100%] blur-3xl opacity-20 transform -rotate-12"></div>
                <div className="absolute w-[700px] h-[400px] bg-black/5 rounded-[100%] translate-y-32 blur-3xl"></div>
              </div>

              {/* The Grid Area */}
              <div className="relative w-[900px] h-[600px] py-20 flex items-center justify-center">
                 <AnimatePresence mode="popLayout">
                   <motion.div 
                     key={timeFilter}
                     initial={{ opacity: 0, scale: 0.9 }}
                     animate={{ opacity: 1, scale: 1 }}
                     exit={{ opacity: 0, scale: 1.1 }}
                     transition={{ duration: 0.5 }}
                     className="relative w-full h-full flex items-center justify-center translate-y-[-50px]"
                   >
                     {MOCK_TREES.slice(0, timeFilter === 'day' ? 3 : timeFilter === 'week' ? 12 : 21).map((tree, i) => (
                       <GardenTile 
                         key={tree.id} 
                         tree={tree} 
                         index={i} 
                         hovered={hoveredId === tree.id}
                         onHover={setHoveredId}
                       />
                     ))}
                   </motion.div>
                 </AnimatePresence>
              </div>
            </div>

            {/* Bottom Info Bar like Forest */}
            <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex gap-12 bg-white/40 backdrop-blur-md px-12 py-5 rounded-[32px] border border-white/50 shadow-2xl">
              <div className="text-center group cursor-help">
                <div className="text-3xl font-bold text-primary-green group-hover:scale-110 transition-transform">
                  12 <span className="text-sm font-normal text-black/40">ساعة</span>
                </div>
                <div className="text-xs text-black/50 mt-1">وقت المراجعة</div>
              </div>
              <div className="w-px h-10 bg-black/10 self-center"></div>
              <div className="text-center group cursor-help">
                <div className="text-3xl font-bold text-primary-green group-hover:scale-110 transition-transform">
                  {MOCK_TREES.length} <span className="text-sm font-normal text-black/40">سورة</span>
                </div>
                <div className="text-xs text-black/50 mt-1">إجمالي الحفظ</div>
              </div>
              <div className="w-px h-10 bg-black/10 self-center"></div>
              <div className="text-center group cursor-help">
                <div className="text-3xl font-bold text-primary-green group-hover:scale-110 transition-transform">
                  {stats.palms} <span className="text-sm font-normal text-black/40">نخلة</span>
                </div>
                <div className="text-xs text-black/50 mt-1">سور طوال</div>
              </div>
            </div>
          </div>

          {/* Footer Stats similar to the UI design */}
          <StatsSection 
            treesCount={MOCK_TREES.length} 
            shrubs={stats.shrubs} 
            palms={stats.palms} 
          />
        </div>
      </main>
    </div>
  );
}
