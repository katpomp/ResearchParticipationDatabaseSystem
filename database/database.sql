-- USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ATTENDANCE
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    event_date DATE,
    event_name VARCHAR(100),
    credits_earned DECIMAL(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);


-- CREDITS
CREATE TABLE credits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    total_credits DECIMAL(5,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);


-- FACULTY
CREATE TABLE Faculty (
    FacultyID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(50),
    LastName VARCHAR(50),
    Email VARCHAR(100) UNIQUE,
    UserID INT,
    FOREIGN KEY (UserID) REFERENCES users(id)
);


-- FACULTY PHONE
CREATE TABLE FacultyPhoneNumber (
    FacultyID INT,
    FacultyPhoneNumber VARCHAR(15),
    PRIMARY KEY (FacultyID, FacultyPhoneNumber),
    FOREIGN KEY (FacultyID) REFERENCES Faculty(FacultyID)
);


-- STUDENT
CREATE TABLE Student (
    StudentID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(50),
    LastName VARCHAR(50),
    Email VARCHAR(100) UNIQUE,
    UserID INT,
    FOREIGN KEY (UserID) REFERENCES users(id)
);


-- STUDENT PHONE
CREATE TABLE StudentPhoneNumber (
    StudentID INT,
    PhoneNumber VARCHAR(15),
    PRIMARY KEY (StudentID, PhoneNumber),
    FOREIGN KEY (StudentID) REFERENCES Student(StudentID)
);


-- RESEARCHER
CREATE TABLE Researcher (
    ResearcherID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(50),
    LastName VARCHAR(50),
    Email VARCHAR(100) UNIQUE,
    UserID INT,
    FOREIGN KEY (UserID) REFERENCES users(id)
);


-- RESEARCHER PHONE
CREATE TABLE ResearcherPhoneNumber (
    ResearcherID INT,
    ResearcherPhoneNumber VARCHAR(15),
    PRIMARY KEY (ResearcherID, ResearcherPhoneNumber),
    FOREIGN KEY (ResearcherID) REFERENCES Researcher(ResearcherID)
);


-- STUDY
CREATE TABLE Study (
    StudyID INT AUTO_INCREMENT PRIMARY KEY,
    StudyTitle VARCHAR(200),
    Description TEXT,
    Status VARCHAR(20),
    StartDate DATE,
    EndDate DATE,
    ResearcherID INT,
    FOREIGN KEY (ResearcherID) REFERENCES Researcher(ResearcherID)
);


-- MENTOR
CREATE TABLE Mentor (
    FacultyID INT,
    ResearcherID INT,
    PRIMARY KEY (FacultyID, ResearcherID),
    FOREIGN KEY (FacultyID) REFERENCES Faculty(FacultyID),
    FOREIGN KEY (ResearcherID) REFERENCES Researcher(ResearcherID)
);


-- EMAIL NOTIFICATION
CREATE TABLE EmailNotification (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    Subject VARCHAR(200),
    MessageBody TEXT,
    SentDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    ResearcherID INT,
    StudentID INT,
    FOREIGN KEY (ResearcherID) REFERENCES Researcher(ResearcherID),
    FOREIGN KEY (StudentID) REFERENCES Student(StudentID)
);


-- CITI TRAINING
CREATE TABLE CITITraining (
    TrainingID INT AUTO_INCREMENT PRIMARY KEY,
    CompletionDate DATE,
    ExpiryDate DATE,
    CertificateURL VARCHAR(255),
    ResearcherID INT,
    FOREIGN KEY (ResearcherID) REFERENCES Researcher(ResearcherID)
);


-- LOCATION
CREATE TABLE Location (
    BuildingName VARCHAR(100),
    RoomNumber VARCHAR(20),
    PRIMARY KEY (BuildingName, RoomNumber)
);


-- IN PERSON SESSION
CREATE TABLE InPersonSession (
    SessionID INT AUTO_INCREMENT PRIMARY KEY,
    SessionDate DATE,
    Duration INT,
    AttendanceStatus VARCHAR(20),
    StudyID INT,
    StudentID INT,
    ResearcherID INT,
    BuildingName VARCHAR(100),
    RoomNumber VARCHAR(20),

    FOREIGN KEY (StudyID) REFERENCES Study(StudyID),
    FOREIGN KEY (StudentID) REFERENCES Student(StudentID),
    FOREIGN KEY (ResearcherID) REFERENCES Researcher(ResearcherID),
    FOREIGN KEY (BuildingName, RoomNumber)
        REFERENCES Location(BuildingName, RoomNumber)
);