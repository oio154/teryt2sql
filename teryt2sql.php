package main

import (
	"fmt"

	"github.com/gin-gonic/gin"

	"time"

	uuid "github.com/nu7hatch/gouuid"

	"log"

	"database/sql"

	_ "github.com/go-sql-driver/mysql"

	"io/ioutil"
)

func dbConn() (db *sql.DB){
	db, err := sql.Open("mysql", "pz:akademia13@/pzdatabase")
	if err != nil{
		panic(err.Error())
	}
	return db
}

func generateUUID() string{
	uuid, err := uuid.NewV4();
	if err != nil{
		log.Fatal(err)
	}
	stringUuid := uuid.String()
	log.Print("[UUID] New UUID generated: "+stringUuid)
	return stringUuid
}

func sqlStoreUUID(uuid string, ip string){
	db := dbConn()
	defer db.Close()
	//var version string
	//db.QueryRow("SELECT VERSION()").Scan(&version)
	//log.Print("[DB] Connected to:", version)
	//qr, _ := db.Prepare("INSERT INTO uuid(uuid,ip) VALUES(?,?)")
	//qr.Exec(uuid,ip)
	db.Exec("INSERT INTO uuid(uuid,ip) VALUES(?,?)", uuid, ip)
	
}



func CORSMiddleware() gin.HandlerFunc {
    return func(c *gin.Context) {

        c.Header("Access-Control-Allow-Origin", "*")
        c.Header("Access-Control-Allow-Credentials", "true")
        c.Header("Access-Control-Allow-Headers", "Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization, accept, origin, Cache-Control, X-Requested-With")
        c.Header("Access-Control-Allow-Methods", "POST,HEAD,PATCH, OPTIONS, GET, PUT")

        if c.Request.Method == "OPTIONS" {
            c.AbortWithStatus(204)
            return
        }

        c.Next()
    }
}



func main(){
	//gin.SetMode(gin.ReleaseMode)
	r := gin.Default();
	r.Use(CORSMiddleware())

	goAppInstanceUuid, _ := uuid.NewV4();
	_ = goAppInstanceUuid
	
	
	r.GET("/ping", func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")
		time.Sleep(3*time.Second)
		//fmt.Printf("\n%+v\n", c.Request.Header)
		//fmt.Printf("%+v\n", c.ClientIP())
		c.JSON(200, gin.H{
			"message": "pong",
			"key": c.Query("k"),
			"port": "3001",
		})
	})

	r.GET("/uuid", func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")
		//time.Sleep(3*time.Second)
		uuid1 := generateUUID()
		c.JSON(200, gin.H{
			"UUID": uuid1,
		})
	})

	r.GET("/sessionid", func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")
		//time.Sleep(1*time.Second)
		uuid1 := generateUUID()
		c.JSON(200, gin.H{
			"sessionID": uuid1,
		})
		ipAddr := c.ClientIP()
		sqlStoreUUID(uuid1, ipAddr)
	})

	
	r.GET("/sessionid/:uuid", checkUserSessionGet)

	r.GET("/userdata/:uuid", userLevel)

	r.POST("/login", loginUser)

	r.POST("/echo",echo)

	r.GET("/teryt", teryt)
	r.GET("/teryt/:w", terytW)
	r.GET("/teryt/:w/:p", terytP)
	r.GET("/teryt/:w/:p/:g", terytG)



	r.GET("/test", func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")

		type size struct{
			Width int
			Height int
		}

		type n struct{
			Id int
			A int
			B int
			C size `json:"obiectsize"`
		}

		var b []n
		for i:=0 ; i<10 ; i++{

			
			var j n
			var s size
			s.Height =100
			s.Width =234
			j.C=s
			j.A=4
			j.Id = i
			b = append( b, j )
		}
		c.JSON(201,b)
	})

	r.Run(":3001")
} // main







// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
type user struct{
	SessionIDexists int `json:"sessionIDexists"`
} 

type userlevel struct{
	UserLevel int `json:"userLevel"`
}

func sqlcheckUserSession(uuid string, ip string) user{
	var u user
	db := dbConn()
	defer db.Close()
	//qr, _ :=db.Prepare("SELECT count(*) as cnt, 3 FROM uuid where uuid=? and ip=?")
	//qr.QueryRow(uuid,ip).Scan(&u.SessionIDexists,&u.Userlevel)
	db.QueryRow("SELECT count(*) as cnt FROM uuid where uuid=? and ip=?",uuid,ip).Scan(&u.SessionIDexists)
	if u.SessionIDexists == 1{
		db.Exec("UPDATE uuid set t=now() where uuid=? and ip=?", uuid, ip)
	}
	return u
}

func checkUserSessionGet(c *gin.Context) {
	c.Header("Access-Control-Allow-Origin", "*")
	uuid1 := c.Param("uuid")
	ipAddr := c.ClientIP()
	log.Printf("[CHK id] %s / %s", uuid1, ipAddr )
	//time.Sleep(1*time.Second)
	ans := sqlcheckUserSession(uuid1,ipAddr)
	// c.JSON(200,gin.H{
	// 	"sessionIDexists": ans.SessionIDexists,
	// 	"userlevel": ans.Userlevel,
	// })
	c.JSON(200,ans)
}

func sqlcheckUserLevel(uuid string, ip string) userlevel{
	var ul userlevel
	
	db := dbConn()
	defer db.Close()

	db.QueryRow("SELECT IFNULL(ulevel,0) FROM uuid where uuid=? and ip=? LIMIT 1",uuid,ip).Scan(&ul.UserLevel)
	
	return ul
}

func userLevel(c *gin.Context) {
	c.Header("Access-Control-Allow-Origin", "*")
	//time.Sleep(3*time.Second)
	uuid := c.Param("uuid")
	ip := c.ClientIP()
	fmt.Println(sqlcheckUserLevel(uuid,ip))
	// c.JSON(200,gin.H{
	// 	"userLevel": 99,
	// })
	ans := sqlcheckUserLevel(uuid,ip)
	c.JSON(200,ans)
}





type LOGIN struct{
    USER string `json:"user"`
    PASS string `json:"pass"`
	SESS string `json:"sess"`
}

func sqlLoginUser(user string, pass string, uuid string, ip string){
	time.Sleep(5*time.Second)
	db := dbConn()
	defer db.Close()
	
	dbret, err := db.Exec(` 	update uuid u set 
					u.ulevel = (select usertype from users where (nick=? or email=?) and pass=PASSWORD(?) limit 1),
					u.users_id = (select id from users where (nick=? or email=?) and pass=PASSWORD(?) limit 1)
				where 
				u.uuid=? and
				u.ip=?`, user,user,pass,user,user,pass,uuid,ip)
	if err != nil{
		log.Panic(err)
	}
	log.Print(dbret)
	
}


func loginUser(c *gin.Context){
	var SALT = "ToWYh7BOpQjnsqsLTpVoKotpGKQjbTuokdmi5uHKT4iXJ6zcWGjcefEUljPFLpGD";
	var login LOGIN
	c.BindJSON(&login)
	fmt.Println(login.PASS + SALT)
	sqlLoginUser(login.USER, login.PASS+SALT, login.SESS, c.ClientIP())
	//c.AbortWithStatus(403)
	return
}


func echo(c *gin.Context){
	// time.Sleep((1/3)*time.Second)
	body, _ := ioutil.ReadAll(c.Request.Body)
    println(string(body))
}



/// TERYT



type TerytW struct{
	WOJ string `json:"woj"`
	NAZWA string `json:"name"`
} 

func teryt(c *gin.Context){
	var w TerytW
	var Arw []TerytW
	_ = w
	q := "%"+c.Query("q")+"%"
	println(q)
	db := dbConn()
	defer db.Close()
	
	resoults, err := db.Query(`select WOJ, NAZWA from TERYT_TERC where POW='' and GMI='' and RODZ=0 AND NAZWA like ?`,q)
	if err != nil {
		log.Panic(err.Error())
	}

	for resoults.Next(){
		err := resoults.Scan(&w.WOJ, &w.NAZWA)
		if err != nil{
			log.Panic(err.Error())
		}
		//fmt.Println(w)
		Arw = append(Arw, w)
		//fmt.Println(Arw)

	}

	c.JSON(201,Arw)

}

func terytW(c *gin.Context){
	w := c.Param("w")
	fmt.Println(w)
	db := dbConn()
	defer db.Close()

	
}
func terytP(c *gin.Context){
	w := c.Param("w")
	p := c.Param("p")
	fmt.Println(w + "/" + p)
	
}
func terytG(c *gin.Context){
	w := c.Param("w")
	p := c.Param("p")
	g := c.Param("g")
	fmt.Println(w + "/" + p + "/" + g)

}

// ////////////////////////////////////////////////////////////////////////////////////////////////////////////// /////
